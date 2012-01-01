<?
include '../scat.php';
include '../lib/txn.php';

$details= array();

$txn= (int)$_REQUEST['txn'];
$id= (int)$_REQUEST['id'];

if (!$txn || !$id) die_jsonp('No transaction or item specified');

$q= "DELETE FROM txn_line WHERE txn = $txn AND id = $id";

$r= $db->query($q);
if (!$r) {
  die(json_encode(array('error' => 'Query failed. ' . $db->error,
                        'query' => $q)));
}
if (!$db->affected_rows) {
  die(json_encode(array('error' => "Unable to delete line.")));
}

$txn= txn_load($db, $txn);

echo json_encode(array('txn' => $txn, 'removed' => $id));
