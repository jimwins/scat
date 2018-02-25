<?
include '../scat.php';
include '../lib/item.php';

$code= $_REQUEST['code'];
$name= $_REQUEST['name'];
$msrp= $_REQUEST['retail_price'];

if (!$code)
  die_jsonp('Must specify a code.');
if (!$name)
  die_jsonp('Must specify a name.');
if (!$msrp)
  die_jsonp('Must specify a price.');

$code= $db->escape($code);
$name= $db->escape($name);
$msrp= $db->escape($msrp);

$q= "INSERT INTO item
        SET code = '$code', name = '$name', retail_price = '$msrp',
            brand = 0,
            taxfree = 0, minimum_quantity = 0, active = 1";

$r= $db->query($q)
  or die_query($db, $q);

$item_id= $db->insert_id;

if ($_REQUEST['barcode']) {
  $bar= $db->escape($_REQUEST['barcode']);
  $q= "INSERT INTO barcode SET code = '$bar', item = $item_id, quantity = 1";
  $r= $db->query($q)
    or die_query($db, $q);
}

/* Link to vendor items */
$q= "UPDATE vendor_item
        SET vendor_item.item = $item_id
      WHERE vendor_item.code = '$code'";
$db->query($q)
  or die_query($db, $q);

$q= "UPDATE vendor_item, barcode
        SET vendor_item.item = $item_id
      WHERE vendor_item.barcode = barcode.code
        AND barcode.item = $item_id";
$db->query($q)
  or die_query($db, $q);

$item= item_load($db, $item_id);

echo jsonp(array('item' => $item));
