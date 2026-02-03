<?php
// 防止重复安装
if(file_exists('config.php') && filesize('config.php') > 0){
    die("<h1>系统已安装</h1><p>如需重装，请手动删除根目录下的 config.php 文件。</p>");
}

$msg = "";
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $db_host = $_POST['db_host'];
    $db_name = $_POST['db_name'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    $admin_user = $_POST['admin_user'];
    $admin_pass = $_POST['admin_pass'];
    $safe_code  = $_POST['safe_code'];

    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 建表 SQL
        $sql = "
        SET NAMES utf8mb4;
        CREATE TABLE IF NOT EXISTS `products` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `price` decimal(10,2) NOT NULL,
          `type` tinyint(1) DEFAULT 0 COMMENT '0一次性 1循环',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `cards` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `pid` int(11) NOT NULL,
          `card_info` text NOT NULL,
          `status` tinyint(1) DEFAULT 0,
          `order_id` varchar(64) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `orders` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `out_trade_no` varchar(64) NOT NULL,
          `pid` int(11) NOT NULL,
          `contact` varchar(64) NOT NULL,
          `money` decimal(10,2) NOT NULL,
          `status` tinyint(1) DEFAULT 0,
          `create_time` int(11) DEFAULT NULL,
          `pay_time` int(11) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `site_logs` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `ip` varchar(50) DEFAULT NULL,
          `time` int(11) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `settings` (
          `k` varchar(32) NOT NULL,
          `v` text,
          PRIMARY KEY (`k`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        INSERT IGNORE INTO `settings` (`k`, `v`) VALUES 
        ('site_title', '自动发卡平台'),
        ('site_name', '自动发卡'),
        ('notice', '欢迎光临，本站24小时自动发货！'),
        ('bg_type', '2'), 
        ('pay_alipay', '1'),
        ('pay_wxpay', '1');
        ";
        $pdo->exec($sql);

        // 写入 config.php
        $txt = "<?php
\$db_host = '$db_host';
\$db_user = '$db_user';
\$db_pass = '$db_pass';
\$db_name = '$db_name';

\$pay_config = [
    'pid' => '{$_POST['pay_pid']}',
    'key' => '{$_POST['pay_key']}',
    'api_url' => '{$_POST['pay_url']}'
];

\$admin_user = '$admin_user';
\$admin_pass = '$admin_pass';
\$safe_code  = '$safe_code';

try {
    \$pdo = new PDO(\"mysql:host=\$db_host;dbname=\$db_name\", \$db_user, \$db_pass);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException \$e) { die(\"数据库连接失败\"); }
?>";
        
        if(file_put_contents('config.php', $txt)){
            echo "<script>alert('安装成功！请务必记住安全码：$safe_code');window.location.href='index.php';</script>";
            exit;
        } else { $msg = "写入文件失败，请检查目录权限是否为 755 或 777"; }

    } catch(PDOException $e) { $msg = "数据库连接失败：" . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>安装向导</title><meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://lib.baomitu.com/twitter-bootstrap/4.6.1/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f8f9fa;padding:40px 0;}.wrap{max-width:500px;margin:0 auto;background:#fff;padding:30px;border-radius:10px;box-shadow:0 0 20px rgba(0,0,0,0.05);}</style>
</head><body>
<div class="wrap"><h3 class="text-center mb-4">🚀 发卡系统安装</h3>
<?php if($msg) echo "<div class='alert alert-danger'>$msg</div>"; ?>
<form method="post">
    <h6 class="text-muted border-bottom pb-2">数据库设置</h6>
    <div class="form-group"><input class="form-control" name="db_host" value="127.0.0.1" placeholder="数据库地址" required></div>
    <div class="form-group"><input class="form-control" name="db_name" placeholder="数据库名" required></div>
    <div class="form-group"><input class="form-control" name="db_user" placeholder="数据库账号" required></div>
    <div class="form-group"><input class="form-control" name="db_pass" placeholder="数据库密码" required></div>
    
    <h6 class="text-muted border-bottom pb-2 mt-4">管理员设置</h6>
    <div class="form-group"><input class="form-control" name="admin_user" value="admin" placeholder="后台账号" required></div>
    <div class="form-group"><input class="form-control" name="admin_pass" value="123456" placeholder="后台密码" required></div>
    <div class="form-group"><input class="form-control" name="safe_code" value="666" placeholder="设置安全码(很重要)" required></div>

    <h6 class="text-muted border-bottom pb-2 mt-4">支付接口 (可后台改)</h6>
    <div class="form-group"><input class="form-control" name="pay_pid" placeholder="商户ID"></div>
    <div class="form-group"><input class="form-control" name="pay_key" placeholder="商户密钥"></div>
    <div class="form-group"><input class="form-control" name="pay_url" value="https://www.mazfu.com/xpay/epay/submit.php" placeholder="接口地址"></div>

    <button class="btn btn-primary btn-block mt-4">立即安装</button>
</form></div></body></html>