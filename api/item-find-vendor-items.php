<?
include '../scat.php';
include '../lib/item.php';

$id= (int)$_REQUEST['id'];
if (!$id)
  die_jsonp("You need to supply an item.");

$q= "UPDATE vendor_item, item
        SET vendor_item.item = item.id
      WHERE (vendor_item.item IS NULL OR vendor_item.item = 0)
        AND vendor_item.code = item.code
        AND item.id = $id";

$r= $db->query($q)
  or die_query($db, $q);

$q= "UPDATE vendor_item, barcode
        SET vendor_item.item = barcode.item
      WHERE (vendor_item.item IS NULL OR vendor_item.item = 0)
        AND vendor_item.barcode = barcode.code
        AND barcode.item = $id";

$r= $db->query($q)
  or die_query($db, $q);

$vendor_items= item_load_vendor_items($db, $id);

echo jsonp(array('vendor_items' => $vendor_items));
