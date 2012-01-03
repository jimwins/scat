<?
include '../scat.php';
include '../lib/txn.php';

bcscale(2);

$txn_id= (int)$_REQUEST['txn'];
$id= (int)$_REQUEST['id'];

if (!$txn_id || !$id)
  die_jsonp("No transaction or payment specified.");

$txn= txn_load($db, $txn_id);

if ($txn['paid'])
  die_jsonp("Transaction is fully paid, can't remove payments.");

// add payment record
$q= "DELETE FROM payment WHERE id = $id AND txn = $txn_id";
$r= $db->query($q)
  or die_query($db, $q);

echo generate_jsonp(array('txn' => txn_load($db, $id),
                          'payments' => txn_load_payments($db, $id)));
