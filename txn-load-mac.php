<?
require 'scat.php';

$type= $_REQUEST['type'];
$number= (int)$_REQUEST['number'];

if (!$type || !$number) die("no transaction specified.");

# xxx escaping
$q= "SELECT id FROM txn WHERE type = '$type' AND number = $number";
$r= $db->query($q);
$row= $r->fetch_row();
$txn= $row[0];

$fn= $_FILES['src']['tmp_name'];

if (!$fn) die("no file uploaded");

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

$db->query($q);
$db->query("LOAD DATA LOCAL INFILE '$fn' INTO TABLE macorder FIELDS TERMINATED BY '\t' OPTIONALLY ENCLOSED BY '\"' IGNORE 1 LINES") or die("unable to load data");
echo "Loaded ", $db->affected_rows, " rows from file.";

$q= "INSERT INTO txn_line (txn, line, item, ordered, shipped)
     SELECT $txn txn, line, (SELECT id FROM item WHERE code = item_no) item, ordered, shipped FROM macorder";

$db->query($q);
echo "Loaded ", $db->affected_rows, " rows into purchase order.";
