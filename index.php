<?php 
session_start();
if(!file_exists('config.php')){ header("Location: install.php"); exit; }
require 'config.php'; 

$settings = [];
$stmt = $pdo->query("SELECT * FROM settings");
while($row = $stmt->fetch()){ $settings[$row['k']] = $row['v']; }

$bg_style = "background-color: #f0f9f0;"; 
if(($settings['bg_type']??'0') == '2') $bg_style = "background: url('https://api.dujin.org/bing/1920.php') no-repeat center center fixed; background-size: cover;";
elseif(($settings['bg_type']??'0') == '1') $bg_style = "background: url('{$settings['bg_url']}') no-repeat center center fixed; background-size: cover;";

$user_ip = $_SERVER['REMOTE_ADDR'];
$last = $pdo->query("SELECT time FROM site_logs WHERE ip='$user_ip' ORDER BY id DESC LIMIT 1")->fetchColumn();
if(!$last || (time()-$last > 60)) $pdo->prepare("INSERT INTO site_logs (ip,time) VALUES (?,?)")->execute([$user_ip,time()]);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($settings['site_title']??''); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="manifest.json">
    <link href="https://lib.baomitu.com/twitter-bootstrap/4.6.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body { <?php echo $bg_style; ?> }
        .container { background: rgba(255, 255, 255, 0.95); border-radius: 10px; padding: 20px; margin-top: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .btn-buy { background-color: #ffc107; color: #fff; font-weight: bold; }
        .stock-tag { padding: 2px 5px; border-radius: 3px; background: #eee; font-size: 12px; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand font-weight-bold" href="#"><?php echo htmlspecialchars($settings['site_name']??''); ?></a>
        <div class="navbar-nav ml-auto">
            <a class="nav-link" href="query.php"><i class="fa fa-search"></i> 查询</a>
            <?php if(isset($_SESSION['is_admin'])): ?>
                <a class="nav-link text-warning" href="admin.php"><i class="fa fa-cog"></i> 后台</a>
                <a class="nav-link" href="admin.php?logout=1">退出</a>
            <?php else: ?>
                <a class="nav-link" href="admin_login.php"><i class="fa fa-user"></i> 登录</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container">
    <div class="alert alert-info"><i class="fa fa-bullhorn"></i> 公告：<?php echo htmlspecialchars($settings['notice']??''); ?></div>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white font-weight-bold">商品列表</div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead><tr><th>商品名称</th><th class="text-center">价格</th><th class="text-center">操作</th></tr></thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM cards WHERE pid = p.id AND status = 0) as real_stock, (SELECT COUNT(*) FROM cards WHERE pid = p.id) as all_cards FROM products p ORDER BY id DESC");
                    while($row = $stmt->fetch()){
                        $stock = ($row['type'] == 1) ? ($row['all_cards'] > 0 ? 999 : 0) : $row['real_stock'];
                    ?>
                    <tr>
                        <td>
                            <?php if($row['type']==1) echo '<span class="badge badge-info">循环</span> '; ?>
                            <?php echo htmlspecialchars($row['name']); ?>
                            <div class="mt-1"><?php echo $stock>0 ? "<span class='stock-tag text-success'>库存: ".($row['type']==1?'充足':$stock)."</span>" : "<span class='badge badge-secondary'>缺货</span>"; ?></div>
                        </td>
                        <td class="text-center text-danger font-weight-bold" style="vertical-align:middle;">¥<?php echo $row['price']; ?></td>
                        <td class="text-center" style="vertical-align:middle;">
                            <button class="btn btn-sm <?php echo $stock>0?'btn-buy':'btn-light'; ?>" <?php echo $stock>0?'':'disabled'; ?> onclick="buy(<?php echo $row['id']; ?>,'<?php echo addslashes($row['name']); ?>','<?php echo $row['price']; ?>')">购买</button>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="buyModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">购买确认</h5><button class="close" data-dismiss="modal">&times;</button></div>
        <form action="pay.php" method="POST" target="_blank">
            <div class="modal-body">
                <input type="hidden" name="pid" id="modal_pid">
                <div class="form-group"><label>商品</label><input type="text" class="form-control" id="modal_name" readonly></div>
                <div class="form-group"><label>价格</label><input type="text" class="form-control text-danger" id="modal_price" readonly></div>
                <div class="form-group"><label>联系方式</label><input class="form-control" name="contact" required placeholder="QQ或邮箱 (用于查询)"></div>
                <div class="form-group"><label>支付方式</label>
                    <select name="type" class="form-control">
                        <?php if(($settings['pay_alipay']??'1')=='1') echo '<option value="alipay">支付宝</option>'; ?>
                        <?php if(($settings['pay_wxpay']??'1')=='1') echo '<option value="wxpay">微信支付</option>'; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary btn-block">立即支付</button></div>
        </form>
    </div></div>
</div>
<script src="https://lib.baomitu.com/jquery/3.6.0/jquery.min.js"></script>
<script src="https://lib.baomitu.com/twitter-bootstrap/4.6.1/js/bootstrap.bundle.min.js"></script>
<script>function buy(id, name, price) { $('#modal_pid').val(id); $('#modal_name').val(name); $('#modal_price').val(price); $('#buyModal').modal('show'); }</script>
</body></html>