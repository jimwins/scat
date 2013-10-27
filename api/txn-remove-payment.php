<?
include '../scat.php';
include '../lib/txn.php';

bcscale(2);

$txn_id= (int)$_REQUEST['txn'];
$id= (int)$_REQUEST['id'];
$admin= (int)$_REQUEST['admin'];

if (!$txn_id || !$id)
  die_jsonp("No transaction or payment specified.");

$txn= txn_load($db, $txn_id);

if ($txn['paid'] && !$admin)
  die_jsonp("Transaction is fully paid, can't remove payments.");

$db->start_transaction()
  or die_query($db, "START TRANSACTION");

// add payment record
$q= "DELETE FROM payment WHERE id = $id AND txn = $txn_id";
$r= $db->query($q)
  or die_query($db, $q);

if ($txn['paid']) {
  $q= "UPDATE txn SET paid = NULL WHERE id = $txn_id";
  $r= $db->query($q)
    or die_query($db, $q);
}

$db->commit()
  or die_query($db, "COMMIT");

echo jsonp(array('txn' => txn_load($db, $txn_id),
                          'payments' => txn_load_payments($db, $txn_id)));
