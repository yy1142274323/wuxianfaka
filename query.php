<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$tradeNo = trim((string)($_GET['trade_no'] ?? $_POST['trade_no'] ?? ''));
$contact = trim((string)($_GET['contact'] ?? $_POST['contact'] ?? ''));
$orders = [];

if ($tradeNo !== '') {
    $stmt = $pdo->prepare('SELECT o.*, p.name AS product_name, p.type AS product_type FROM orders o LEFT JOIN products p ON p.id = o.pid WHERE o.out_trade_no = ? LIMIT 1');
    $stmt->execute([$tradeNo]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $orders[] = $row;
    }
} elseif ($contact !== '') {
    $stmt = $pdo->prepare('SELECT o.*, p.name AS product_name, p.type AS product_type FROM orders o LEFT JOIN products p ON p.id = o.pid WHERE o.contact = ? ORDER BY o.id DESC LIMIT 20');
    $stmt->execute([$contact]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$siteName = setting($settings, 'site_name', 'OOOAI 发卡');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>订单查询 - <?php echo e($siteName); ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php"><span class="brand-icon">O</span><span><?php echo e($siteName); ?></span></a>
    <nav class="nav-actions"><a href="index.php">首页</a><a href="admin_login.php">后台</a></nav>
</header>
<main class="store-shell narrow">
    <section class="query-panel">
        <h1>订单查询</h1>
        <form method="get" class="query-form">
            <input name="trade_no" value="<?php echo e($tradeNo); ?>" placeholder="订单号">
            <input name="contact" value="<?php echo e($contact); ?>" placeholder="联系方式">
            <button class="primary-btn">查询</button>
        </form>
    </section>

    <?php if (($tradeNo !== '' || $contact !== '') && !$orders): ?>
        <div class="empty-state"><h3>没有找到订单</h3><p>请检查订单号或联系方式是否填写正确。</p></div>
    <?php endif; ?>

    <?php foreach ($orders as $order): ?>
        <article class="order-card">
            <div class="order-head">
                <div>
                    <span class="muted">订单号</span>
                    <h2><?php echo e($order['out_trade_no']); ?></h2>
                </div>
                <span class="status <?php echo (int)$order['status'] === 1 ? 'paid' : 'pending'; ?>">
                    <?php echo (int)$order['status'] === 1 ? '已确认' : '待确认'; ?>
                </span>
            </div>
            <dl class="order-meta">
                <div><dt>商品</dt><dd><?php echo e($order['product_name'] ?? '未知商品'); ?></dd></div>
                <div><dt>金额</dt><dd>¥<?php echo e((string)$order['money']); ?></dd></div>
                <div><dt>提交时间</dt><dd><?php echo e(format_time((int)$order['create_time'])); ?></dd></div>
            </dl>
            <?php $cards = order_cards($pdo, $order); ?>
            <?php if ((int)$order['status'] === 1 && $cards): ?>
                <div class="card-result">
                    <strong>卡密信息</strong>
                    <?php foreach ($cards as $card): ?>
                        <pre><?php echo e($card); ?></pre>
                    <?php endforeach; ?>
                </div>
            <?php elseif ((int)$order['status'] === 1): ?>
                <div class="alert alert-error">订单已确认，但还没有可展示的卡密，请联系管理员。</div>
            <?php else: ?>
                <div class="alert alert-info">订单已提交，等待后台确认后会显示卡密。</div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</main>
</body>
</html>
