<?
include '../scat.php';
include '../lib/txn.php';

$txn_id= (int)$_REQUEST['txn'];
$id= (int)$_REQUEST['id'];
$admin= (int)$_REQUEST['admin'];

if (!$txn_id || !$id)
  die_jsonp("No transaction or payment specified.");

$txn= new Transaction($db, $txn_id);

try {
  $txn->removePayment($id, $admin);
} catch (Exception $e) {
  die_jsonp($e->getMessage());
}

echo jsonp(array('txn' => txn_load($db, $txn_id),
                          'payments' => txn_load_payments($db, $txn_id)));
