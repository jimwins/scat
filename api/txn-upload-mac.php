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

$q= "CREATE TEMPORARY TABLE macorder (
       line int,
       status varchar(255),
       item_no varchar(255),
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

$q= "LOAD DATA LOCAL INFILE '$fn'
     INTO TABLE macorder
     FIELDS TERMINATED BY '\t' OPTIONALLY ENCLOSED BY '\"'
     IGNORE 1 LINES";
$db->query($q)
  or die_query($db, $q);

echo "Loaded ", $db->affected_rows, " rows from file.<br>";

$q= "START TRANSACTION";
$db->query($q)
  or die_query($db, $q);

# Make sure we have all the items
$q= "INSERT IGNORE INTO item (code, brand, name, retail_price, active)
     SELECT item_no AS code,
            70 AS brand,
            description AS name,
            msrp AS retail_price,
            1 AS active
       FROM macorder
      WHERE msrp > 0";
$db->query($q)
  or die_query($db, $q);
echo "Loaded ", $db->affected_rows, " items from order.<br>";

$q= "INSERT IGNORE INTO barcode (item, code, quantity)
     SELECT (SELECT id FROM item WHERE item_no = code) AS item,
            REPLACE(REPLACE(barcode, 'e-', ''), 'u-', '') AS code,
            1 AS quantity
      FROM macorder";
$db->query($q)
  or die_query($db, $q);
echo "Loaded ", $db->affected_rows, " new barcodes from order.<br>";

$q= "INSERT INTO txn_line (txn, line, item, ordered, retail_price)
     SELECT $txn_id txn, line,
            (SELECT id FROM item WHERE code = item_no) item,
            shipped AS ordered, net
       FROM macorder
      WHERE shipped";
$db->query($q)
  or die_query($db, $q);

echo "Loaded ", $db->affected_rows, " rows into purchase order.";

$db->commit()
  or die_jsonp($db->error);

$out= ob_get_contents();
ob_end_clean();

echo jsonp(array("result" => $out));
