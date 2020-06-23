<?
include '../scat.php';
include '../lib/txn.php';
include '../lib/pole.php';

$id= (int)$_REQUEST['txn'];
if (!$id)
  die_jsonp("no transaction specified.");

$txn= \Titi\Model::factory('Txn')->find_one($id);
$txn->clearItems();

// XXX replace when Txn serializes same as txn_load gives us
$txn= txn_load($db, $id);

echo jsonp(array("success" => "Cleared transaction.",
                 "txn" => $txn,
                 "items" => []));
