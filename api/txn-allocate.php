<?
include '../scat.php';

$txn= (int)$_REQUEST['txn'];
if (!$txn)
  die_jsonp("no transaction specified.");

$q= "SELECT paid FROM txn WHERE id = $txn";
$r= $db->query($q)
  or die_jsonp($db->error);

$row= $r->fetch_row();

if ($row[0]) {
  die_jsonp("This order is already paid!");
}

$q= "UPDATE txn_line SET allocated = ordered WHERE txn = $txn";
$r= $db->query($q)
  or die_jsonp($db->error);
$lines= $r->num_rows;

$q= "UPDATE txn SET filled = NOW() WHERE id = $txn";
$r= $db->query($q)
  or die_jsonp($db->error);

generate_jsonp(array("success" => "Allocated all lines.",
                     "lines" => $r->num_rows));
