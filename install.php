<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (app_installed()) {
    http_response_code(403);
    exit('<!doctype html><meta charset="utf-8"><title>已安装</title><style>body{font-family:Arial,sans-serif;padding:40px}</style><h1>系统已安装</h1><p>如需重装，请先备份数据，然后手动删除根目录下的 <code>config.php</code>。</p>');
}

$message = '';
$messageType = 'error';

function install_schema(PDO $pdo): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS products (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            type TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 single use, 1 reusable',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at INT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS cards (
            id INT NOT NULL AUTO_INCREMENT,
            pid INT NOT NULL,
            card_info TEXT NOT NULL,
            status TINYINT(1) NOT NULL DEFAULT 0,
            order_id VARCHAR(64) NULL,
            created_at INT NULL,
            PRIMARY KEY (id),
            INDEX idx_cards_pid_status (pid, status),
            INDEX idx_cards_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS orders (
            id INT NOT NULL AUTO_INCREMENT,
            out_trade_no VARCHAR(64) NOT NULL,
            pid INT NOT NULL,
            contact VARCHAR(128) NOT NULL,
            money DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status TINYINT(1) NOT NULL DEFAULT 0,
            create_time INT NULL,
            pay_time INT NULL,
            remark VARCHAR(255) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_order_no (out_trade_no),
            INDEX idx_orders_contact (contact),
            INDEX idx_orders_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS site_logs (
            id INT NOT NULL AUTO_INCREMENT,
            ip VARCHAR(50) NULL,
            time INT NULL,
            PRIMARY KEY (id),
            INDEX idx_site_logs_ip_time (ip, time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS settings (
            `k` VARCHAR(64) NOT NULL,
            `v` TEXT NULL,
            PRIMARY KEY (`k`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
}

function mysql_identifier(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid database name.');
    }
    return '`' . $name . '`';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim((string)($_POST['db_host'] ?? '127.0.0.1'));
    $dbName = trim((string)($_POST['db_name'] ?? ''));
    $dbUser = trim((string)($_POST['db_user'] ?? ''));
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $adminUser = trim((string)($_POST['admin_user'] ?? 'admin'));
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    $safeCode = (string)($_POST['safe_code'] ?? '');
    $siteName = trim((string)($_POST['site_name'] ?? 'OOOAI 发卡'));

    if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
        $message = '数据库名只能包含字母、数字和下划线。';
    } elseif ($adminUser === '' || strlen($adminPass) < 8 || strlen($safeCode) < 6) {
        $message = '后台密码至少 8 位，安全码至少 6 位。';
    } else {
        try {
            $pdo = new PDO('mysql:host=' . $dbHost . ';charset=utf8mb4', $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $database = mysql_identifier($dbName);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . $database . ' DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE ' . $database);
            install_schema($pdo);

            $defaults = [
                'site_title' => $siteName . ' - 自动发卡系统',
                'site_name' => $siteName,
                'site_description' => '轻量级自动发卡系统，支持商品、卡密、订单和后台管理。',
                'notice' => '欢迎光临，请选择商品并提交订单。',
                'theme_color' => '#176B87',
            ];
            foreach ($defaults as $key => $value) {
                $stmt = $pdo->prepare('REPLACE INTO settings (`k`, `v`) VALUES (?, ?)');
                $stmt->execute([$key, $value]);
            }

            $adminPassHash = password_hash($adminPass, PASSWORD_DEFAULT);
            $safeCodeHash = password_hash($safeCode, PASSWORD_DEFAULT);
            $config = "<?php\n"
                . "declare(strict_types=1);\n\n"
                . "const DB_HOST = " . var_export($dbHost, true) . ";\n"
                . "const DB_NAME = " . var_export($dbName, true) . ";\n"
                . "const DB_USER = " . var_export($dbUser, true) . ";\n"
                . "const DB_PASS = " . var_export($dbPass, true) . ";\n\n"
                . "const ADMIN_USER = " . var_export($adminUser, true) . ";\n"
                . "const ADMIN_PASS_HASH = " . var_export($adminPassHash, true) . ";\n"
                . "const ADMIN_SAFE_CODE_HASH = " . var_export($safeCodeHash, true) . ";\n\n"
                . "\$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';\n"
                . "\$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [\n"
                . "    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n"
                . "    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n"
                . "    PDO::ATTR_EMULATE_PREPARES => false,\n"
                . "]);\n";

            if (!file_put_contents(__DIR__ . '/config.php', $config, LOCK_EX)) {
                throw new RuntimeException('config.php 写入失败，请检查目录权限。');
            }

            redirect('admin_login.php?installed=1');
        } catch (Throwable $e) {
            $message = '安装失败：' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>安装 OOOAI 发卡</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="install-page">
<main class="install-shell">
    <section class="install-panel">
        <div class="brand-mark">OOOAI</div>
        <h1>安装发卡系统</h1>
        <p class="muted">这是空白安装版，不包含任何支付接口、收款码、商户密钥或真实业务数据。</p>
        <?php if ($message): ?>
            <div class="alert alert-error"><?php echo e($message); ?></div>
        <?php endif; ?>
        <form method="post" class="form-grid">
            <h2>数据库</h2>
            <label>数据库地址<input name="db_host" value="<?php echo e($_POST['db_host'] ?? '127.0.0.1'); ?>" required></label>
            <label>数据库名<input name="db_name" value="<?php echo e($_POST['db_name'] ?? ''); ?>" required></label>
            <label>数据库账号<input name="db_user" value="<?php echo e($_POST['db_user'] ?? ''); ?>" required></label>
            <label>数据库密码<input type="password" name="db_pass" value="<?php echo e($_POST['db_pass'] ?? ''); ?>"></label>

            <h2>后台</h2>
            <label>网站名称<input name="site_name" value="<?php echo e($_POST['site_name'] ?? 'OOOAI 发卡'); ?>" required></label>
            <label>后台账号<input name="admin_user" value="<?php echo e($_POST['admin_user'] ?? 'admin'); ?>" required></label>
            <label>后台密码<input type="password" name="admin_pass" minlength="8" required></label>
            <label>安全码<input type="password" name="safe_code" minlength="6" required></label>

            <button class="primary-btn" type="submit">创建空白站点</button>
        </form>
    </section>
</main>
</body>
</html>
