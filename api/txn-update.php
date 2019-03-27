<?
include '../scat.php';
include '../lib/txn.php';

$txn_id= (int)$_REQUEST['txn'];

$txn= txn_load($db, $txn_id);

if (!$txn)
  die_jsonp('No such transaction.');

/* Plain integer values */
foreach(array('number', 'no_rewards') as $key) {
  if (isset($_REQUEST[$key])) {
    $value= (int)$_REQUEST[$key];
    // $key is one of our hardcoded values
    $q= "UPDATE txn SET $key = $value WHERE id = $txn_id";
    $r= $db->query($q)
      or die_query($db, $q);
  }
}

$txn= txn_load($db, $txn_id);

echo jsonp(array('txn' => $txn));
