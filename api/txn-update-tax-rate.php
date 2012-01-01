<?
include '../scat.php';
include '../lib/txn.php';

$txn= (int)$_REQUEST['txn'];
if (!$txn)
  die_jsonp("no transaction specified.");

$tax_rate= (float)$_REQUEST['tax_rate'];
if (!strcmp($_REQUEST['tax_rate'], 'def')) {
  $tax_rate= DEFAULT_TAX_RATE;
}

$q= "UPDATE txn SET tax_rate = $tax_rate WHERE id = $txn";
$r= $db->query($q)
  or die_jsonp($db->error);

$txn= txn_load($db, $txn);

generate_jsonp(array("success" => "Updated tax rate.",
                     "txn" => $txn));
