<?
include '../scat.php';
include '../lib/item.php';

$item_id= (int)$_REQUEST['item'];

$item= item_load($db, $item_id);

if (!$item)
  die_jsonp('No such item.');
if (!$_REQUEST['code'])
  die_jsonp('No barcode.');

$code= $db->escape($_REQUEST['code']);

$q= "DELETE FROM barcode WHERE item = $item_id AND code = '$code'";
$db->query($q)
  or die_query($db, $q);

$item= item_load($db, $item_id);

echo jsonp(array('item' => $item));
