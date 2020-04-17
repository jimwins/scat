<?
include '../scat.php';
include '../lib/txn.php';

$txn_id= (int)$_REQUEST['txn'];
if (!$txn_id)
  die_jsonp("No transaction specified.");

$db->start_transaction();

$txn= new Transaction($db, $txn_id);

$person= (int)$_REQUEST['person'];
if (!$person)
  die_jsonp("No person specified.");

$q= "SELECT id FROM person WHERE id = $person";
$r= $db->query($q)
  or die_query($db, $q);
if (!$r->num_rows)
  die_jsonp("No such person.");

if ($txn->paid)
  $txn->clearLoyalty();

$q= "UPDATE txn SET person_id = $person WHERE id = $txn_id";
$r= $db->query($q)
  or die_query($db, $q);

$txn->person_id= $person;

if ($txn->paid)
  $txn->rewardLoyalty();

$db->commit();

$txn= txn_load($db, $txn_id);
$person= person_load($db, $person);

echo jsonp(array("success" => "Updated person.", "txn" => $txn, "person" => $person));
