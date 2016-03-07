<?
include '../scat.php';
include '../lib/item.php';

$from_id= (int)$_REQUEST['from'];
$from= item_load($db, $from_id);
if (!$from)
  die_jsonp('No such item to merge from.');

$to_code= $_REQUEST['to'];
$to= item_find($db, "code:" . $to_code, 0);
if (!$to[0])
  die_jsonp('No such item to merge to.');
$to_id= $to[0]['id'];

$q= "START TRANSACTION";
$r= $db->query($q)
  or die_jsonp($db->error);

$q= "UPDATE barcode SET item = $to_id WHERE item = $from_id";
$db->query($q)
  or die_query($db, $q);

$q= "UPDATE txn_line SET item = $to_id WHERE item = $from_id";
$db->query($q)
  or die_query($db, $q);

$q= "UPDATE vendor_item SET item = $to_id WHERE item = $from_id";
$db->query($q)
  or die_query($db, $q);

$q= "DELETE FROM item WHERE id = $from_id";
$db->query($q)
  or die_query($db, $q);

$db->commit()
  or die_query($db, "COMMIT");

$to= item_load($db, $to_id);

echo jsonp(array('item' => $to));
