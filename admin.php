<?php
session_start();
if(!file_exists('config.php')){ header("Location: install.php"); exit; }
require 'config.php';
if(!isset($_SESSION['is_admin'])) { header("Location: admin_login.php"); exit; }
if(isset($_GET['logout'])) { session_destroy(); header("Location: admin_login.php"); exit; }

$msg = ""; $page = $_GET['page'] ?? 'dashboard';

// 逻辑处理
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $act = $_POST['action'];
    if($act == 'save_settings'){
        $kvs = ['notice','site_title','site_name','bg_type','bg_url','pay_alipay','pay_wxpay'];
        foreach($kvs as $k) $pdo->prepare("REPLACE INTO settings (k,v) VALUES (?,?)")->execute([$k, $_POST[$k]??'0']);
        $msg = "设置已保存"; $page = 'settings';
    } elseif($act == 'add_product'){
        $pdo->prepare("INSERT INTO products (name,price,type) VALUES (?,?,?)")->execute([$_POST['name'],$_POST['price'],$_POST['type']]);
        $msg = "添加成功"; $page = 'products';
    } elseif($act == 'edit_product'){
        $pdo->prepare("UPDATE products SET name=?,price=?,type=? WHERE id=?")->execute([$_POST['name'],$_POST['price'],$_POST['type'],$_POST['id']]);
        $msg = "修改成功"; $page = 'products';
    } elseif($act == 'del_product'){
        $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$_POST['del_id']]);
        $pdo->prepare("DELETE FROM cards WHERE pid=?")->execute([$_POST['del_id']]);
        $msg = "已删除"; $page = 'products';
    } elseif($act == 'add_cards'){
        $cards = explode("\n", $_POST['content']); $n=0;
        $stmt = $pdo->prepare("INSERT INTO cards (pid,card_info,status) VALUES (?,?,0)");
        foreach($cards as $c) if(trim($c)){ $stmt->execute([$_POST['pid'],trim($c)]); $n++; }
        $msg = "成功导入 $n 张"; $page = 'cards';
    } elseif($act == 'del_card'){
        $pdo->prepare("DELETE FROM cards WHERE id=?")->execute([$_POST['card_id']]);
        $msg = "已删除"; $page = 'cards';
    }
}

// 数据读取
$set = []; foreach($pdo->query("SELECT * FROM settings") as $r) $set[$r['k']]=$r['v'];
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>管理后台</title>
<link href="https://lib.baomitu.com/twitter-bootstrap/4.6.1/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
<style>
body{background:#f4f6f9}.sidebar{background:#fff;min-height:100vh;box-shadow:2px 0 5px rgba(0,0,0,0.05)}
.nav-link{color:#555;padding:12px 20px;margin-bottom:5px}.nav-link.active{background:#007bff;color:#fff}
.nav-link i{width:25px;text-align:center}.card-stat{border:none;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
</style></head><body>
<div class="container-fluid"><div class="row">
    <div class="col-md-2 px-0 sidebar d-none d-md-block">
        <div class="p-4 text-center border-bottom"><h5 class="font-weight-bold"><i class="fa fa-cube text-primary"></i> 发卡后台</h5></div>
        <div class="p-3 nav flex-column">
            <a class="nav-link <?php echo $page=='dashboard'?'active':'';?>" href="?page=dashboard"><i class="fa fa-home"></i> 仪表盘</a>
            <a class="nav-link <?php echo $page=='settings'?'active':'';?>" href="?page=settings"><i class="fa fa-cog"></i> 设置</a>
            <a class="nav-link <?php echo $page=='products'?'active':'';?>" href="?page=products"><i class="fa fa-box"></i> 商品</a>
            <a class="nav-link <?php echo $page=='cards'?'active':'';?>" href="?page=cards"><i class="fa fa-ticket-alt"></i> 卡密</a>
            <a class="nav-link <?php echo $page=='orders'?'active':'';?>" href="?page=orders"><i class="fa fa-list"></i> 订单</a>
            <a class="nav-link text-danger mt-3" href="?logout=1"><i class="fa fa-power-off"></i> 退出</a>
            <a class="nav-link text-muted" href="index.php" target="_blank"><i class="fa fa-external-link-alt"></i> 前台</a>
        </div>
    </div>
    <div class="col-md-10 p-4">
        <div class="d-md-none mb-3 bg-white p-3 rounded shadow-sm"><a href="?page=dashboard">仪表盘</a> <a href="?page=products" class="ml-3">商品</a></div>
        <?php if($msg) echo "<div class='alert alert-success'>$msg</div>"; ?>

        <?php if($page=='dashboard'): 
            $today = strtotime(date('Y-m-d'));
            $income = $pdo->query("SELECT sum(money) FROM orders WHERE status=1 AND pay_time>=$today")->fetchColumn()?:'0.00';
            $orders = $pdo->query("SELECT count(*) FROM orders WHERE status=1 AND pay_time>=$today")->fetchColumn();
            $stock = $pdo->query("SELECT count(*) FROM cards WHERE status=0")->fetchColumn();
        ?>
            <div class="row mb-4">
                <div class="col-md-4"><div class="card card-stat bg-primary text-white p-3"><h3>¥ <?php echo $income; ?></h3><div class="small">今日收入</div></div></div>
                <div class="col-md-4"><div class="card card-stat bg-success text-white p-3"><h3><?php echo $orders; ?> 单</h3><div class="small">今日订单</div></div></div>
                <div class="col-md-4"><div class="card card-stat bg-info text-white p-3"><h3><?php echo $stock; ?> 张</h3><div class="small">总库存</div></div></div>
            </div>
            <div class="card shadow-sm border-0"><div class="card-header bg-white font-weight-bold">最新访客</div>
            <table class="table table-sm mb-0"><thead><tr><th>IP</th><th>时间</th></tr></thead><tbody>
                <?php foreach($pdo->query("SELECT * FROM site_logs ORDER BY id DESC LIMIT 5") as $r) echo "<tr><td>{$r['ip']}</td><td>".date('m-d H:i',$r['time'])."</td></tr>";?>
            </tbody></table></div>
        
        <?php elseif($page=='settings'): ?>
            <div class="card shadow-sm border-0"><div class="card-header bg-white font-weight-bold">系统设置</div><div class="card-body"><form method="post">
                <input type="hidden" name="action" value="save_settings">
                <div class="form-row"><div class="col"><label>网站名</label><input class="form-control" name="site_name" value="<?php echo htmlspecialchars($set['site_name']??'');?>"></div>
                <div class="col"><label>标题</label><input class="form-control" name="site_title" value="<?php echo htmlspecialchars($set['site_title']??'');?>"></div></div>
                <div class="form-group mt-2"><label>公告</label><textarea class="form-control" name="notice"><?php echo htmlspecialchars($set['notice']??'');?></textarea></div>
                <div class="form-group"><label>背景: </label> 
                    <label><input type="radio" name="bg_type" value="0" <?php echo ($set['bg_type']??0)==0?'checked':'';?>> 纯色</label>
                    <label class="ml-2"><input type="radio" name="bg_type" value="2" <?php echo ($set['bg_type']??0)==2?'checked':'';?>> Bing壁纸</label>
                    <label class="ml-2"><input type="radio" name="bg_type" value="1" <?php echo ($set['bg_type']??0)==1?'checked':'';?>> 自定义</label>
                    <input class="form-control mt-1" name="bg_url" placeholder="自定义图片URL" value="<?php echo htmlspecialchars($set['bg_url']??'');?>">
                </div>
                <div class="form-group"><label>支付开关: </label>
                    <label class="ml-2"><input type="checkbox" name="pay_alipay" value="1" <?php echo ($set['pay_alipay']??1)?'checked':'';?>> 支付宝</label>
                    <label class="ml-2"><input type="checkbox" name="pay_wxpay" value="1" <?php echo ($set['pay_wxpay']??1)?'checked':'';?>> 微信</label>
                </div>
                <button class="btn btn-primary">保存</button>
            </form></div></div>

        <?php elseif($page=='products'): ?>
            <div class="row"><div class="col-md-4"><div class="card shadow-sm border-0 mb-3"><div class="card-header bg-white font-weight-bold">添加商品</div><div class="card-body">
                <form method="post"><input type="hidden" name="action" value="add_product">
                <input class="form-control mb-2" name="name" placeholder="名称" required><input class="form-control mb-2" name="price" placeholder="价格" required>
                <select class="form-control mb-2" name="type"><option value="0">一次性</option><option value="1">循环</option></select>
                <button class="btn btn-primary btn-block">添加</button></form></div></div></div>
            <div class="col-md-8"><div class="card shadow-sm border-0"><div class="card-header bg-white font-weight-bold">商品列表</div>
                <table class="table table-hover mb-0"><thead><tr><th>ID</th><th>名称</th><th>价格</th><th>库存</th><th>操作</th></tr></thead><tbody>
                <?php foreach($pdo->query("SELECT p.*,(SELECT count(*) FROM cards WHERE pid=p.id AND status=0) as s FROM products p ORDER BY id DESC") as $r): ?>
                <tr><td><?php echo $r['id'];?></td><td><?php echo htmlspecialchars($r['name']);?></td><td><?php echo $r['price'];?></td><td><?php echo $r['type']==1?'循环':$r['s'];?></td>
                <td><button class="btn btn-sm btn-outline-primary" onclick="edit(<?php echo htmlspecialchars(json_encode($r));?>)">编辑</button> 
                <form method="post" onsubmit="return confirm('删?')" class="d-inline"><input type="hidden" name="action" value="del_product"><input type="hidden" name="del_id" value="<?php echo $r['id'];?>"><button class="btn btn-sm btn-outline-danger">删</button></form></td></tr>
                <?php endforeach; ?></tbody></table></div></div></div>
            <div class="modal fade" id="editModal"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">编辑</h5><button class="close" data-dismiss="modal">&times;</button></div><form method="post"><div class="modal-body"><input type="hidden" name="action" value="edit_product"><input type="hidden" name="id" id="eid"><input class="form-control mb-2" name="name" id="ename"><input class="form-control mb-2" name="price" id="eprice"><select class="form-control" name="type" id="etype"><option value="0">一次性</option><option value="1">循环</option></select></div><div class="modal-footer"><button class="btn btn-primary">保存</button></div></form></div></div></div>

        <?php elseif($page=='cards'): ?>
            <?php if(isset($_GET['pid'])): $pid=(int)$_GET['pid']; $pn=$pdo->query("SELECT name FROM products WHERE id=$pid")->fetchColumn(); ?>
                <div class="card shadow-sm border-0"><div class="card-header bg-white font-weight-bold">查看卡密: <?php echo htmlspecialchars($pn);?> <a href="?page=cards" class="float-right btn btn-sm btn-light">返回</a></div>
                <table class="table table-sm mb-0"><thead><tr><th>卡密</th><th>状态</th><th>操作</th></tr></thead><tbody>
                <?php foreach($pdo->query("SELECT * FROM cards WHERE pid=$pid ORDER BY id DESC") as $c): ?>
                <tr><td><?php echo htmlspecialchars($c['card_info']);?></td><td><?php echo $c['status']?'已售':'未售';?></td><td><form method="post" class="d-inline"><input type="hidden" name="action" value="del_card"><input type="hidden" name="card_id" value="<?php echo $c['id'];?>"><button class="btn btn-xs btn-outline-danger">删</button></form></td></tr>
                <?php endforeach; ?></tbody></table></div>
            <?php else: ?>
                <div class="row"><div class="col-md-5"><div class="card shadow-sm border-0"><div class="card-header bg-white font-weight-bold">导入卡密</div><div class="card-body">
                <form method="post"><input type="hidden" name="action" value="add_cards"><select name="pid" class="form-control mb-2"><?php foreach($pdo->query("SELECT * FROM products") as $p) echo "<option value='{$p['id']}'>{$p['name']}</option>";?></select><textarea name="content" class="form-control mb-2" rows="5" placeholder="一行一个"></textarea><button class="btn btn-success btn-block">导入</button></form></div></div></div>
                <div class="col-md-7"><div class="card shadow-sm border-0"><div class="card-header bg-white font-weight-bold">库存概览</div><div class="list-group list-group-flush">
                <?php foreach($pdo->query("SELECT p.id,p.name,(SELECT count(*) FROM cards WHERE pid=p.id AND status=0) as s FROM products p") as $r):?>
                <a href="?page=cards&pid=<?php echo $r['id'];?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"><?php echo htmlspecialchars($r['name']);?> <span class="badge badge-primary badge-pill"><?php echo $r['s'];?></span></a>
                <?php endforeach;?></div></div></div></div>
            <?php endif; ?>
        
        <?php elseif($page=='orders'): ?>
            <div class="card shadow-sm border-0"><div class="card-header bg-white font-weight-bold">订单记录</div><table class="table table-hover mb-0"><thead><tr><th>单号</th><th>商品</th><th>联系</th><th>金额</th><th>状态</th><th>时间</th></tr></thead><tbody>
            <?php foreach($pdo->query("SELECT o.*,p.name FROM orders o LEFT JOIN products p ON o.pid=p.id ORDER BY o.id DESC LIMIT 50") as $o):?>
            <tr><td><?php echo $o['out_trade_no'];?></td><td><?php echo htmlspecialchars($o['name']);?></td><td><?php echo htmlspecialchars($o['contact']);?></td><td><?php echo $o['money'];?></td><td><?php echo $o['status']?'<span class="text-success">已付</span>':'<span class="text-muted">未付</span>';?></td><td><?php echo date('m-d H:i',$o['create_time']);?></td></tr>
            <?php endforeach;?></tbody></table></div>
        <?php endif; ?>
    </div>
</div></div>
<script src="https://lib.baomitu.com/jquery/3.6.0/jquery.min.js"></script>
<script src="https://lib.baomitu.com/twitter-bootstrap/4.6.1/js/bootstrap.bundle.min.js"></script>
<script>
function edit(o){ $('#eid').val(o.id);$('#ename').val(o.name);$('#eprice').val(o.price);$('#etype').val(o.type);$('#editModal').modal('show'); }
</script></body></html>