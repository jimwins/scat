<?
include '../scat.php';
include '../lib/txn.php';

$id= (int)$_REQUEST['txn'];
if (!$id)
  die_jsonp("No transaction specified.");

$txn= txn_load($db, $id);

if (!$txn)
  die_jsonp("No such transaction..");

$q= "SELECT COUNT(*) FROM txn_line WHERE txn = $id";
$lines= $db->get_one($q);

if ($lines)
  die_jsonp("Can't delete transaction with items.");

$q= "SELECT COUNT(*) FROM payment WHERE txn = $id";
$lines= $db->get_one($q);

if ($lines)
  die_jsonp("Can't delete transaction with payments.");

$q= "DELETE FROM txn WHERE id = $id";
$r= $db->query($q)
  or die_query($db, $q);

echo jsonp(array('message' => 'Transaction deleted.'));
