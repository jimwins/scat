<?
include '../scat.php';
include '../lib/txn.php';

$id= (int)$_REQUEST['txn'];
if (!$id)
  die_jsonp("No transaction specified.");

$txn= txn_load($db, $id);

if ($txn['paid']) {
  die_jsonp("This order is already paid!");
}

$line= (int)$_REQUEST['line'];

if ($line) {
  $q= "UPDATE txn_line SET allocated = 0 WHERE txn = $id AND id = $line";

  $r= $db->query($q)
    or die_jsonp($db->error);

  $lines= $db->affected_rows;

} else {

  $q= "UPDATE txn_line SET allocated = 0 WHERE txn = $id";
  $r= $db->query($q)
    or die_jsonp($db->error);
  $lines= $db->affected_rows;

}

if ($txn['filled']) {
  $q= "UPDATE txn SET filled = NULL WHERE id = $id";
  $r= $db->query($q)
    or die_jsonp($db->error);
}


$txn= txn_load($db, $id);

echo jsonp(array("success" => "Opened all lines.",
                 "txn" => $txn,
                 "lines" => $lines,
                 "items" => txn_load_items($db, $id)));

