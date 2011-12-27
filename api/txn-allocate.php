<?
include '../scat.php';

$txn= (int)$_REQUEST['txn'];
if (!$txn)
  die_jsonp("no transaction specified.");

$q= "UPDATE txn_line SET allocated = ordered WHERE txn = $txn";
$r= $db->query($q)
  or die_jsonp($db->error);

generate_jsonp(array("success" => "Allocated all lines.",
                     "lines" => $r->num_rows));
