<?php require 'config.php'; ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>查单</title><link href="https://lib.baomitu.com/twitter-bootstrap/4.6.1/css/bootstrap.min.css" rel="stylesheet">
</head><body class="bg-light"><div class="container mt-5"><div class="card"><div class="card-header">订单查询</div><div class="card-body">
<form class="form-inline mb-3"><input name="contact" class="form-control mr-2" placeholder="联系方式/订单号"><button class="btn btn-primary">查询</button> <a href="index.php" class="btn btn-light ml-2">返回</a></form>
<?php
$q = $_GET['contact']??''; $no = $_GET['trade_no']??'';
if($q || $no){
    $val = $no?$no:$q;
    $sql = "SELECT o.*,p.name,p.type FROM orders o JOIN products p ON o.pid=p.id WHERE o.status=1 AND (o.contact=? OR o.out_trade_no=?)";
    $stmt = $pdo->prepare($sql); $stmt->execute([$val,$val]);
    $res = $stmt->fetchAll();
    if(!$res) echo "<div class='alert alert-warning'>未找到已支付订单</div>";
    foreach($res as $r){
        $cards = "";
        if($r['type']==1) $cards = $pdo->query("SELECT card_info FROM cards WHERE pid={$r['pid']} LIMIT 1")->fetchColumn();
        else $cards = $pdo->query("SELECT card_info FROM cards WHERE order_id='{$r['out_trade_no']}'")->fetchColumn();
        echo "<div class='card mb-2'><div class='card-body'><h6>{$r['name']} <small class='text-muted'>".date('Y-m-d H:i',$r['pay_time'])."</small></h6><textarea class='form-control'>".($cards?:'发货失败或库存不足，请联系客服')."</textarea></div></div>";
    }
}
?>
</div></div></div></body></html>