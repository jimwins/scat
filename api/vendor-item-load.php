<?
include '../scat.php';

$id= 0;

$code= $_REQUEST['code'];

if ($code) {
  $code= $db->escape($code);
  $q= "SELECT id FROM vendor_item WHERE code = '$code'";
  $id= $db->get_one($q);
}

if (!$id)
  die_jsonp("No item specified.");

$q= "SELECT vendor_item.id, vendor_item.item, vendor, company vendor_name,
            code, vendor_sku, vendor_item.name,
            retail_price, net_price, promo_price,
            special_order, barcode,
            purchase_quantity
       FROM vendor_item
       JOIN person ON vendor_item.vendor = person.id
       WHERE vendor_item.id = $id
       ORDER BY vendor_item.active DESC";

$vendor_item= $db->get_one_assoc($q);

echo jsonp(array('item' => $vendor_item));
