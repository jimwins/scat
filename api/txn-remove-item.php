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

$q= "DELETE FROM txn_line WHERE txn = $txn_id AND id = $id";

$r= $db->query($q);
if (!$r) die_query($db, $q);
if (!$db->affected_rows) {
  die_jsonp("Unable to delete line.");
}

$txn= txn_load($db, $txn_id);

echo jsonp(array('txn' => $txn, 'removed' => $id));
