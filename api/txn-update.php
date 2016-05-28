<?
include '../scat.php';
include '../lib/txn.php';

$txn_id= (int)$_REQUEST['txn'];

$txn= txn_load($db, $txn_id);

if (!$txn)
  die_jsonp('No such transaction.');

if (isset($_REQUEST['special_order'])) {
  $special= (int)$_REQUEST['special_order'];
  $q= "UPDATE txn
          SET special_order = '$special'
        WHERE id = $txn_id";

  $r= $db->query($q)
    or die_query($db, $q);
}


$txn= txn_load($db, $txn_id);

echo jsonp(array('txn' => $txn));
