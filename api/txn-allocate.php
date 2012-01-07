<?
include '../scat.php';
include '../lib/txn.php';

$id= (int)$_REQUEST['txn'];
if (!$id)
  die_jsonp("no transaction specified.");

$txn= txn_load($db, $id);

if ($txn['paid']) {
  die_jsonp("This order is already paid!");
}

$q= "UPDATE txn_line SET allocated = ordered WHERE txn = $id";
$r= $db->query($q)
  or die_jsonp($db->error);
$lines= $db->affected_rows;

if ($lines) {
  $q= "UPDATE txn SET filled = NOW() WHERE id = $id";
  $r= $db->query($q)
    or die_jsonp($db->error);
}

$txn= txn_load($db, $id);

echo jsonp(array("success" => "Allocated all lines.",
                 "txn" => $txn,
                 "lines" => $lines));
