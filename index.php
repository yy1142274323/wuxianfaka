<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if ($ip !== '') {
    $stmt = $pdo->prepare('SELECT time FROM site_logs WHERE ip = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$ip]);
    $lastVisit = (int)$stmt->fetchColumn();
    if (!$lastVisit || time() - $lastVisit > 60) {
        $log = $pdo->prepare('INSERT INTO site_logs (ip, time) VALUES (?, ?)');
        $log->execute([$ip, time()]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_order') {
    verify_csrf();
    $pid = (int)($_POST['pid'] ?? 0);
    $contact = trim((string)($_POST['contact'] ?? ''));

    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$pid]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        flash_set('商品不存在或已下架。', 'error');
    } elseif ($contact === '') {
        flash_set('请填写联系方式，方便查询订单。', 'error');
    } elseif (product_stock($pdo, $product) < 1) {
        flash_set('库存不足，请选择其他商品。', 'error');
    } else {
        $orderNo = generate_order_no();
        $insert = $pdo->prepare('INSERT INTO orders (out_trade_no, pid, contact, money, status, create_time) VALUES (?, ?, ?, ?, 0, ?)');
        $insert->execute([$orderNo, $pid, $contact, $product['price'], time()]);
        flash_set('订单已提交，等待后台确认。订单号：' . $orderNo);
        redirect('query.php?trade_no=' . urlencode($orderNo));
    }
    redirect('index.php');
}

$products = $pdo->query('SELECT p.*, (SELECT COUNT(*) FROM cards WHERE pid = p.id AND status = 0) AS real_stock, (SELECT COUNT(*) FROM cards WHERE pid = p.id) AS all_cards FROM products p WHERE p.is_active = 1 ORDER BY p.id DESC')->fetchAll(PDO::FETCH_ASSOC);
$flash = flash_get();
$siteName = setting($settings, 'site_name', 'OOOAI 发卡');
$siteTitle = setting($settings, 'site_title', $siteName);
$description = setting($settings, 'site_description', '轻量级自动发卡系统。');
$notice = setting($settings, 'notice', '欢迎光临，请选择商品并提交订单。');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($siteTitle); ?></title>
    <meta name="description" content="<?php echo e($description); ?>">
    <link rel="manifest" href="manifest.json">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">
        <span class="brand-icon">O</span>
        <span><?php echo e($siteName); ?></span>
    </a>
    <nav class="nav-actions">
        <a href="query.php">查订单</a>
        <?php if (admin_logged_in()): ?>
            <a href="admin.php">后台</a>
        <?php else: ?>
            <a href="admin_login.php">登录</a>
        <?php endif; ?>
    </nav>
</header>

<main class="store-shell">
    <section class="hero-band">
        <div>
            <p class="eyebrow">Blank installable source</p>
            <h1><?php echo e($siteName); ?></h1>
            <p><?php echo e($description); ?></p>
        </div>
        <div class="notice-strip">
            <span>公告</span>
            <strong><?php echo e($notice); ?></strong>
        </div>
    </section>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
    <?php endif; ?>

    <section class="section-head">
        <div>
            <h2>商品列表</h2>
            <p>安装后默认是空白站点，请在后台添加商品和卡密。</p>
        </div>
        <a class="ghost-btn" href="query.php">查询已有订单</a>
    </section>

    <?php if (!$products): ?>
        <div class="empty-state">
            <h3>还没有商品</h3>
            <p>登录后台后添加商品、导入卡密，前台会自动显示可购买项目。</p>
            <a class="primary-btn inline" href="admin_login.php">进入后台</a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product):
                $stock = ((int)$product['type'] === 1) ? ((int)$product['all_cards'] > 0 ? 999 : 0) : (int)$product['real_stock'];
                $isReusable = (int)$product['type'] === 1;
            ?>
            <article class="product-card">
                <div class="product-main">
                    <span class="pill"><?php echo $isReusable ? '循环卡密' : '一次性卡密'; ?></span>
                    <h3><?php echo e($product['name']); ?></h3>
                    <?php if (!empty($product['description'])): ?>
                        <p><?php echo e($product['description']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="product-meta">
                    <strong>¥<?php echo e((string)$product['price']); ?></strong>
                    <span><?php echo $stock > 0 ? ($isReusable ? '库存充足' : '库存 ' . $stock) : '缺货'; ?></span>
                </div>
                <form method="post" class="buy-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create_order">
                    <input type="hidden" name="pid" value="<?php echo (int)$product['id']; ?>">
                    <input name="contact" placeholder="QQ / 邮箱 / 手机" <?php echo $stock > 0 ? 'required' : 'disabled'; ?>>
                    <button class="primary-btn" <?php echo $stock > 0 ? '' : 'disabled'; ?>>提交订单</button>
                </form>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<script src="assets/js/app.js"></script>
</body>
</html>
