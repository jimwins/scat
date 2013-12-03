<?
include '../scat.php';
include '../lib/txn.php';

$id= (int)$_REQUEST['txn'];
if (!$id)
  die_jsonp("no transaction specified.");

$txn= txn_load($db, $id);

if ($txn['paid']) {
  die_jsonp("This order is already paid!");
}

$q= "UPDATE txn_line SET allocated = ordered WHERE txn = $id";
$r= $db->query($q)
  or die_jsonp($db->error);
$lines= $db->affected_rows;

if ($lines || !$txn['filled']) {
  $q= "UPDATE txn SET filled = NOW() WHERE id = $id";
  $r= $db->query($q)
    or die_jsonp($db->error);
}

$txn= txn_load($db, $id);

if ($txn['total']) {
  $sock= socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
  if (@socket_connect($sock, '127.0.0.1', 1888)) {
    $product= 'Total Due';
    $price= $txn['total'];
    socket_write($sock,
                 sprintf("\x0d\x0a%-19.19s\x0a\x0d$%18.2f ",
                         $product, $price));
  }
}

echo jsonp(array("success" => "Allocated all lines.",
                 "txn" => $txn,
                 "lines" => $lines));
