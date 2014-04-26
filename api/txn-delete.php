<?
include '../scat.php';
include '../lib/txn.php';

$id= (int)$_REQUEST['txn'];
if (!$id)
  die_jsonp("No transaction specified.");

$txn= new Transaction($db, $id);

if (!$txn)
  die_jsonp("No such transaction..");

if ($txn->hasPayments())
  die_jsonp("Can't delete transaction with payments.");

if ($txn->hasItems())
  die_jsonp("Can't delete transaction with items.");

$q= "DELETE FROM txn WHERE id = $id";
$r= $db->query($q)
  or die_query($db, $q);

echo jsonp(array('message' => 'Transaction deleted.'));
