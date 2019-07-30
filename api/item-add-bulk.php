<?
include '../scat.php';
include '../lib/item.php';

$vendor= (int)$_REQUEST['vendor'];
$code= $_REQUEST['code'];

if (!$vendor)
  die_jsonp('Must specify a vendor.');
if (!$code)
  die_jsonp('Must specify a code.');

$code= $db->escape($code);

/* Add the items */
$q= "INSERT INTO item (code, name, brand, retail_price,
                       discount_type, discount,
                       active, minimum_quantity, purchase_quantity,
                       prop65, hazmat, oversized)
     SELECT vi.code, vi.name, 0, vi.retail_price,
            NULL, NULL, 1, 0, vi.purchase_quantity,
            vi.prop65, vi.hazmat, vi.oversized
       FROM vendor_item vi
       LEFT JOIN item ON item = item.id
      WHERE vendor = $vendor
        AND vi.active
        AND vi.code LIKE '$code'
        AND item.id IS NULL";

$r= $db->query($q)
  or die_query($db, $q);

$count= $db->affected_rows;

/* Link to vendor items */
$q= "UPDATE vendor_item, item
        SET vendor_item.item = item.id
      WHERE vendor_item.code = item.code
        AND vendor = $vendor AND vendor_item.code LIKE '$code'";
$db->query($q)
  or die_query($db, $q);

/* Add barcodes */
$q= "INSERT IGNORE INTO barcode (code, item, quantity)
     SELECT barcode AS code, item, 1
       FROM vendor_item
      WHERE vendor = $vendor AND code LIKE '$code'
        AND item";
$r= $db->query($q)
  or die_query($db, $q);

echo jsonp(array('items' => $count));
