<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (admin_logged_in()) {
    redirect('admin.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $safeCode = (string)($_POST['safe_code'] ?? '');
    if (login_admin($username, $password, $safeCode)) {
        redirect('admin.php');
    }
    $error = '账号、密码或安全码错误。';
}

$siteName = setting($settings, 'site_name', 'OOOAI 发卡');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>后台登录 - <?php echo e($siteName); ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="login-page">
<main class="login-card">
    <div class="brand-mark">OOOAI</div>
    <h1>后台登录</h1>
    <?php if (isset($_GET['installed'])): ?>
        <div class="alert alert-success">安装完成，请登录后台添加商品和卡密。</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
    <?php endif; ?>
    <form method="post" class="form-grid">
        <?php echo csrf_field(); ?>
        <label>账号<input name="username" autocomplete="username" required></label>
        <label>密码<input type="password" name="password" autocomplete="current-password" required></label>
        <label>安全码<input type="password" name="safe_code" required></label>
        <button class="primary-btn">登录</button>
    </form>
    <a class="back-link" href="index.php">返回前台</a>
</main>
</body>
</html>
