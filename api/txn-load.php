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

$txn= txn_load($db, $id);

$items= txn_load_items($db, $id);

$payments= txn_load_payments($db, $id);

$q= "SELECT id, entered, content
       FROM txn_note
      WHERE txn = $id
      ORDER BY entered ASC";

$notes= array();
while ($row= $r->fetch_assoc()) {
  /* force numeric values to numeric type */
  $notes[]= $row;
}

echo generate_jsonp(array('txn' => $txn,
                          'items' => $items,
                          'payments' => $payments,
                          'notes' => $notes));
