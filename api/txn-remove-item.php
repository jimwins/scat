<?
include '../scat.php';
include '../lib/txn.php';

$details= array();

$txn_id= (int)$_REQUEST['txn'];
$id= (int)$_REQUEST['id'];

if (!$txn_id || !$id) die_jsonp('No transaction or item specified');

$txn= txn_load($db, $txn_id);
if ($txn['paid']) {
  die_jsonp("This order is already paid!");
}

$q= "DELETE FROM txn_line WHERE txn_id = $txn_id AND id = $id";

$r= $db->query($q);
if (!$r) die_query($db, $q);
if (!$db->affected_rows) {
  die_jsonp("Unable to delete line.");
}

// XXX error handling
txn_apply_discounts($db, $txn_id);

$txn= txn_load_full($db, $txn_id);
$txn['removed']= $id;

echo jsonp($txn);
