<?php
require 'config.php';
$pid = intval($_POST['pid']); $contact = trim($_POST['contact']); $type = $_POST['type'];
// 安全检查
$set=[]; foreach($pdo->query("SELECT * FROM settings") as $r) $set[$r['k']]=$r['v'];
if(($type=='alipay' && !($set['pay_alipay']??1)) || ($type=='wxpay' && !($set['pay_wxpay']??1))) die("通道关闭");

$p = $pdo->query("SELECT * FROM products WHERE id=$pid")->fetch();
if(!$p) die("商品不存在");
$stock = ($p['type']==1) ? $pdo->query("SELECT count(*) FROM cards WHERE pid=$pid")->fetchColumn() 
                         : $pdo->query("SELECT count(*) FROM cards WHERE pid=$pid AND status=0")->fetchColumn();
if($stock < 1) die("<script>alert('库存不足');history.back();</script>");

$no = date("YmdHis").rand(1000,9999);
$pdo->prepare("INSERT INTO orders (out_trade_no,pid,contact,money,create_time) VALUES (?,?,?,?,?)")->execute([$no,$pid,$contact,$p['price'],time()]);

$data = ["pid"=>$pay_config['pid'],"type"=>$type,"out_trade_no"=>$no,"notify_url"=>"http://{$_SERVER['HTTP_HOST']}/notify.php","return_url"=>"http://{$_SERVER['HTTP_HOST']}/query.php?trade_no=$no","name"=>$p['name'],"money"=>$p['price'],"sitename"=>"自动发卡"];
ksort($data); $s=''; foreach($data as $k=>$v) if($v!='') $s.="$k=$v&";
$data['sign'] = md5(substr($s,0,-1).$pay_config['key']); $data['sign_type']='MD5';
?>
<form id="p" action="<?php echo $pay_config['api_url'];?>" method="post">
<?php foreach($data as $k=>$v) echo "<input type='hidden' name='$k' value='$v'>";?>
</form><script>document.getElementById('p').submit();</script>