<?
include '../scat.php';

$vendor= $_REQUEST['vendor'];
$item= $_REQUEST['item'];
$vendor_sku= $_REQUEST['vendor_sku'];
if (empty($vendor) || empty($item) || empty($vendor_sku))
  die_jsonp("You need to supply at least a vendor, item and SKU.");

if (empty($_REQUEST['code'])) {
  $_REQUEST['code']= $_REQUEST['vendor_sku'];
}

$list= array();
foreach(array('item', 'vendor', 'code', 'vendor_sku', 'name',
              'retail_price', 'net_price', 'promo_price',
              'barcode', 'purchase_quantity', 'special_order') as $field) {
  $value= trim($_REQUEST[$field]);
  /* Turn empty strings into NULL, escape others and wrap in quotes */
  $value= ($value != '') ?  "'" . $db->escape($value) . "'" : 'NULL';
  $list[]= "$field = $value, ";
}

$fields= substr(join('', $list), 0, -2); # chop off last ", "

$q= "INSERT INTO vendor_item SET $fields";
$r= $db->query($q)
  or die_query($db, $q);

$q= "SELECT vendor_item.id, vendor_item.item, vendor, company vendor_name,
            code, vendor_sku, vendor_item.name,
            retail_price, net_price, promo_price,
            special_order,
            purchase_quantity
       FROM vendor_item
       JOIN person ON vendor_item.vendor = person.id
      WHERE vendor_item.id = " . $db->insert_id;

$vendor_item= $db->get_one_assoc($q);

echo jsonp(array('vendor_item' => $vendor_item));
