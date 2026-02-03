<?php
require 'config.php';
$d = $_GET; $sign = $d['sign']; unset($d['sign']); unset($d['sign_type']);
ksort($d); $s=''; foreach($d as $k=>$v) if($v!='') $s.="$k=$v&";
if(md5(substr($s,0,-1).$pay_config['key']) == $sign && $d['trade_status']=='TRADE_SUCCESS'){
    $no = $d['out_trade_no'];
    $o = $pdo->query("SELECT * FROM orders WHERE out_trade_no='$no'")->fetch();
    if($o && $o['status']==0){
        $pdo->beginTransaction();
        try{
            $pdo->exec("UPDATE orders SET status=1,pay_time=".time()." WHERE id={$o['id']}");
            $type = $pdo->query("SELECT type FROM products WHERE id={$o['pid']}")->fetchColumn();
            if($type==0){
                $c = $pdo->query("SELECT id FROM cards WHERE pid={$o['pid']} AND status=0 LIMIT 1 FOR UPDATE")->fetch();
                if($c) $pdo->exec("UPDATE cards SET status=1,order_id='$no' WHERE id={$c['id']}");
            }
            $pdo->commit();
        }catch(Exception $e){ $pdo->rollBack(); }
    }
    echo "success";
} else echo "fail";
?>