<?
include '../scat.php';
include '../lib/txn.php';

$txn_id= (int)$_REQUEST['txn'];

$txn= txn_load($db, $txn_id);

if (!$txn)
  die_jsonp('No such transaction.');

$txn= txn_load($db, $txn_id);

echo jsonp(array('txn' => $txn));
