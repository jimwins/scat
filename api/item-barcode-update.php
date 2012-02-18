<?
include '../scat.php';
include '../lib/item.php';

$item_id= (int)$_REQUEST['item'];

$item= item_load($db, $item_id);

if (!$item)
  die_jsonp('No such item.');
if (!$_REQUEST['code'])
  die_jsonp('No barcode.');
$quantity= (int)$_REQUEST['quantity'];
if (!$quantity) $quantity= 1;

$code= $db->escape($_REQUEST['code']);

$q= "INSERT INTO barcode
        SET item = $item_id, code = '$code', quantity = $quantity
         ON DUPLICATE KEY
     UPDATE quantity = VALUES(quantity)";
$db->query($q)
  or die_query($db, $q);

$item= item_load($db, $item_id);

echo jsonp(array('item' => $item));
