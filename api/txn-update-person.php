<?
include '../scat.php';
include '../lib/txn.php';

$txn_id= (int)$_REQUEST['txn'];
if (!$txn_id)
  die_jsonp("No transaction specified.");

$txn= txn_load($db, $txn_id);

$person= (int)$_REQUEST['person'];
if (!$person)
  die_jsonp("No person specified.");

$q= "SELECT id FROM person WHERE id = $person";
$r= $db->query($q)
  or die_query($db, $q);
if (!$r->num_rows)
  die_jsonp("No such person.");

$q= "UPDATE txn SET person = $person WHERE id = $txn_id";
$r= $db->query($q)
  or die_query($db, $q);

$txn= txn_load($db, $txn_id);

echo jsonp(array("success" => "Updated person.", "txn" => $txn));
