<?
require '../scat.php';
require '../lib/txn.php';

$txn_id= (int)$_REQUEST['txn'];

if (!$txn_id)
  die_jsonp("No transaction specified.");

$txn= txn_load($db, $txn_id);

if ($txn['type'] != 'vendor')
  die_jsonp("That's not a vendor transaction!");

$fn= $_FILES['src']['tmp_name'];

if (!$fn)
  die_jsonp("No file uploaded");

ob_start();

$file= fopen($fn, 'r');
$line= fgets($file);
fclose($file);

$q= "SELECT id FROM brand WHERE name = 'New Item'";
$new_item= $db->get_one($q)
  or die_query($db, $q);

$q= "CREATE TEMPORARY TABLE vendor_order (
       line int,
       status varchar(255),
       item_no varchar(255),
       item int unsigned,
       sku varchar(255),
       cust_item varchar(255),
       description varchar(255),
       ordered int,
       shipped int,
       backordered int,
       msrp decimal(9,2),
       discount decimal(9,2),
       net decimal(9,2),
       unit varchar(255),
       ext decimal(9,2),
       barcode varchar(255),
       account_no varchar(255),
       po_no varchar(255),
       order_no varchar(255),
       bo_no varchar(255),
       invoice_no varchar(255),
       box_no varchar(255))";

$db->query($q)
  or die_query($db, $q);

// SLS order?
if (preg_match('/^linenum,qty/', $line)) {
  $q= "LOAD DATA LOCAL INFILE '$fn'
       INTO TABLE vendor_order
       FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"'
       IGNORE 1 LINES
       (line, @shipped, sku, cust_item, description, @upc,
        msrp, net, box_no, ext)
       SET barcode = REPLACE(@upc, 'UPC->', ''),
           ordered = @shipped, shipped = @shipped";
  $db->query($q)
    or die_query($db, $q);

  $q= "UPDATE vendor_order
          SET item_no = IF(barcode,
                           IFNULL((SELECT item.code
                                     FROM item
                                     JOIN barcode ON barcode.item = item.id
                                    WHERE vendor_order.barcode = barcode.code
                                    LIMIT 1),
                                  sku),
                           sku)";
  $db->query($q)
    or die_query($db, $q);

  echo "Loaded ", $db->affected_rows, " rows from file.<br>";

} elseif (preg_match('/^Vendor Name	Assortment Item Number/', $line)) {
  // MacPherson assortment
  $q= "LOAD DATA LOCAL INFILE '$fn'
       INTO TABLE vendor_order
       FIELDS TERMINATED BY '\t'
       IGNORE 1 LINES
       (@vendor_name, @asst_item_no, item_no, @asst_description, @shipped,
        @change_flag, @change_date, sku, description, unit, msrp, net,
        @customer, @product_code_type, barcode, @reno, @elgin, @atlanta,
        @catalog_code, @purchase_unit, @purchase_qty, cust_item,
        @pending_msrp, @pending_date, @pending_net, @promo_net, @promo_name,
        @abc_flag, @vendor, @group_code, @catalog_description)
       SET ordered = @shipped, shipped = @shipped";
  $db->query($q)
    or die_query($db, $q);

  echo "Loaded ", $db->affected_rows, " rows from file.<br>";

} elseif (preg_match('/^C2F Product #/', $line)) {
  // C2F
  $q= "LOAD DATA LOCAL INFILE '$fn'
       INTO TABLE vendor_order
       FIELDS TERMINATED BY '\t'
       LINES TERMINATED BY '\r\n'
       IGNORE 1 LINES
       (item_no, @qty, @uom, description, msrp, net, barcode)
       SET ordered = @qty, shipped = @qty";
  $db->query($q)
    or die_query($db, $q);

  echo "Loaded ", $db->affected_rows, " rows from file.<br>";
} elseif (preg_match('/^,Name,MSRP/', $line)) {
  // CSV
  $q= "LOAD DATA LOCAL INFILE '$fn'
       INTO TABLE vendor_order
       FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"'
       IGNORE 1 LINES
       (item_no, description, @msrp, @sale, @net, @qty, @ext, barcode)
       SET ordered = @qty, shipped = @qty,
           msrp = REPLACE(@msrp, '$', ''), net = REPLACE(@net, '$', '')";
  $db->query($q)
    or die_query($db, $q);

  echo "Loaded ", $db->affected_rows, " rows from file.<br>";
} else {
  // MacPherson's order
  $q= "LOAD DATA LOCAL INFILE '$fn'
       INTO TABLE vendor_order
       FIELDS TERMINATED BY '\t' OPTIONALLY ENCLOSED BY '\"'
       IGNORE 1 LINES
       (line, status, item_no, sku, cust_item, description, ordered,
        shipped, backordered, msrp, discount, net, unit, ext, barcode,
        account_no, po_no, order_no, bo_no, invoice_no, box_no)";
  $db->query($q)
    or die_query($db, $q);

  echo "Loaded ", $db->affected_rows, " rows from file.<br>";
}

$q= "START TRANSACTION";
$db->query($q)
  or die_query($db, $q);

# Make sure we have all the items
$q= "INSERT IGNORE INTO item (code, brand, name, retail_price, active)
     SELECT item_no AS code,
            $new_item AS brand,
            description AS name,
            msrp AS retail_price,
            1 AS active
       FROM vendor_order
      WHERE msrp > 0 AND IFNULL(unit,'') != 'AS'";
$db->query($q)
  or die_query($db, $q);
echo "Loaded ", $db->affected_rows, " items from order.<br>";

# Make sure all the items are active and update order with item ids
$q= "UPDATE item, vendor_order
        SET item.active = 1,
            vendor_order.item = item.id
      WHERE item_no = code";
$db->query($q)
  or die_query($db, $q);
echo "Activated ", $db->affected_rows, " items from order.<br>";

# Make sure we know all the barcodes
$q= "INSERT IGNORE INTO barcode (item, code, quantity)
     SELECT (SELECT id FROM item WHERE item_no = code) AS item,
            REPLACE(REPLACE(barcode, 'E-', ''), 'U-', '') AS code,
            1 AS quantity
      FROM vendor_order
     WHERE barcode != ''";
$db->query($q)
  or die_query($db, $q);
echo "Loaded ", $db->affected_rows, " new barcodes from order.<br>";

# Link items to vendor items
$q= "UPDATE vendor_item, vendor_order
        SET vendor_item.item = vendor_order.item
      WHERE vendor_item.code = vendor_order.item_no";
$db->query($q)
  or die_query($db, $q);
echo "Linked ", $db->affected_rows, " items to vendor items.<br>";

# Add items to order
$q= "INSERT INTO txn_line (txn, item, ordered, allocated, retail_price)
     SELECT $txn_id txn, item,
            ordered, shipped, net
       FROM vendor_order
      WHERE (shipped OR backordered) AND item IS NOT NULL";
$db->query($q)
  or die_query($db, $q);

echo "Loaded ", $db->affected_rows, " rows into purchase order.<br>";

$db->commit()
  or die_jsonp($db->error);

$q= "SELECT CAST((SUM(shipped) / SUM(ordered)) * 100 AS DECIMAL(9,1))
       FROM vendor_order";
$item_rate= $db->get_one($q);
$q= "SELECT CAST((SUM(shipped > 0) / SUM(ordered > 0)) * 100 AS DECIMAL(9,1))
       FROM vendor_order";
$sku_rate= $db->get_one($q);
echo "Fill rate by item: $item_rate%, by SKU: $sku_rate%.";

$out= ob_get_contents();
ob_end_clean();

echo jsonp(array("result" => $out));
