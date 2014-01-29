<?
include '../scat.php';
include '../lib/txn.php';

$id= (int)$_REQUEST['txn'];
if (!$id)
  die_jsonp("no transaction specified.");

if (!txn_apply_discounts($db, $id)) {
  die_jsonp("Unable to apply discounts.");
}

$txn= txn_load_full($db, $id);

echo jsonp($txn);
