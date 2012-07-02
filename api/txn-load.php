<?
include '../scat.php';
include '../lib/txn.php';

$id= (int)$_REQUEST['id'];

$type= $_REQUEST['type'];
$number= (int)$_REQUEST['number'];

if (!$id && $type) {
  $q= "SELECT id FROM txn
        WHERE type = '". $db->real_escape_string($type) ."'
          AND number = $number";
  $r= $db->query($q);

  if (!$r->num_rows)
    die_jsonp("No such transaction.");

  $row= $r->fetch_row();
  $id= $row[0];
}

if (!$id)
  die_jsonp("No transaction specified.");

echo jsonp(txn_load_full($db, $id));
