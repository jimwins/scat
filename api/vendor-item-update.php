<?
include '../scat.php';

$vendor_item_id= (int)$_REQUEST['id'];

//$vendor_item= vendor_item_load($db, $vendor_item_id);

if (!$vendor_item_id)
  die_jsonp('No such vendor_item.');

$db->start_transaction();

foreach(array('item', 'vendor', 'code', 'vendor_sku', 'name',
              'retail_price', 'net_price', 'promo_price',
              'barcode', 'purchase_quantity', 'special_order') as $key) {
  if (isset($_REQUEST[$key])) {
    $value= trim($_REQUEST[$key]);
    /* Turn empty strings into NULL, escape others and wrap in quotes */
    $value= ($value != '') ?  "'" . $db->escape($value) . "'" : 'NULL';
    $q= "UPDATE vendor_item SET $key = $value WHERE id = $vendor_item_id";

    $r= $db->query($q)
      or die_query($db, $q);
  }
}

$db->commit();

$vendor_item= $db->get_one_assoc("SELECT * FROM vendor_item
                                   WHERE id = $vendor_item_id");

echo jsonp(array('vendor_item' => $vendor_item));
