<?php
session_start();
if(!file_exists('config.php')){ header("Location: install.php"); exit; }
require 'config.php';
if(isset($_SESSION['is_admin'])){ header("Location: admin.php"); exit; }

$step = (isset($_GET['code']) && $_GET['code'] === $safe_code) ? 2 : 1;
$error = "";

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    if(isset($_POST['input_code'])){
        if($_POST['input_code'] === $safe_code){ header("Location: ?code=$safe_code"); exit; }
        else { $error = "安全码错误"; }
    } elseif(isset($_POST['user'])){
        if($_POST['user'] == $admin_user && $_POST['pass'] == $admin_pass){
            $_SESSION['is_admin'] = true; header("Location: admin.php"); exit;
        } else { sleep(2); $error = "账号或密码错误"; $step = 2; }
    }
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>后台登录</title>
<link href="https://lib.baomitu.com/twitter-bootstrap/4.6.1/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#eee;height:100vh;display:flex;align-items:center;justify-content:center;}.box{width:350px;padding:30px;background:#fff;border-radius:10px;box-shadow:0 10px 20px rgba(0,0,0,0.1);}</style>
</head><body>
<div class="box">
    <?php if($step == 1): ?>
        <h5 class="text-center mb-4">🛡️ 安全验证</h5>
        <?php if($error) echo "<div class='alert alert-danger py-1'>$error</div>"; ?>
        <form method="post"><input type="password" name="input_code" class="form-control mb-3 text-center" placeholder="请输入安全码" required autofocus><button class="btn btn-primary btn-block">验证</button></form>
    <?php else: ?>
        <h5 class="text-center mb-4">管理员登录</h5>
        <?php if($error) echo "<div class='alert alert-danger py-1'>$error</div>"; ?>
        <form method="post" action="?code=<?php echo $safe_code; ?>">
            <input class="form-control mb-3" name="user" placeholder="账号" required autofocus>
            <input type="password" class="form-control mb-3" name="pass" placeholder="密码" required>
            <button class="btn btn-dark btn-block">登录</button>
        </form>
    <?php endif; ?>
    <div class="text-center mt-3"><a href="index.php" class="text-muted small">返回前台</a></div>
</div></body></html>