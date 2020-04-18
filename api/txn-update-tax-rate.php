<?
include '../scat.php';
include '../lib/txn.php';

$txn_id= (int)$_REQUEST['txn'];
if (!$txn_id) $txn_id= (int)$_REQUEST['pk'];
if (!$txn_id)
  die_jsonp("no transaction specified.");

$txn= txn_load($db, $txn_id);
if ($txn['paid']) {
  die_jsonp("This order is already paid!");
}

$tax_rate= (float)$_REQUEST['tax_rate'];
if (!$tax_rate) $tax_rate= (float)$_REQUEST['value'];
if (!strcmp($tax_rate, 'def')) {
  $tax_rate= DEFAULT_TAX_RATE;
}

$q= "UPDATE txn SET tax_rate = $tax_rate WHERE id = $txn_id";
$r= $db->query($q)
  or die_jsonp($db->error);

$txn= txn_load($db, $txn_id);

echo jsonp(array("success" => "Updated tax rate.", "txn" => $txn));
