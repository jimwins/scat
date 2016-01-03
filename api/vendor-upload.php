<?
require '../scat.php';

$vendor_id= (int)$_REQUEST['vendor'];

if (!$vendor_id)
  die_jsonp("No vendor specified.");

$fn= $_FILES['src']['tmp_name'];

if (!$fn)
  die_jsonp("No file uploaded");

$file= fopen($fn, 'r');
$line= fgets($file);
fclose($file);

ob_start();

if (preg_match('/MACITEM.*\.zip$/i', $_FILES['src']['name'])) {
  $q= "CREATE TEMPORARY TABLE macitem (
    item_no VARCHAR(32),
    sku VARCHAR(10),
    name VARCHAR(255),
    retail_price DECIMAL(9,2),
    net_price DECIMAL(9,2),
    promo_price DECIMAL(9,2),
    pending_msrp DECIMAL(9,2),
    pending_date VARCHAR(32),
    pending_net DECIMAL(9,2),
    barcode VARCHAR(32),
    purchase_quantity INT,
    category VARCHAR(64))";

  $db->query($q)
    or die_query($db, $q);

  $base= basename($_FILES['src']['name'], '.zip');

  $q= "LOAD DATA LOCAL INFILE 'zip://$fn#$base.txt'
            INTO TABLE macitem
          CHARACTER SET 'latin1'
          FIELDS TERMINATED BY '\t'
          IGNORE 1 LINES
          (@changed, @change_date, item_no, sku, name, @unit_of_sale,
           retail_price, net_price, @customer, @product_code_type,
           barcode, @reno, @elgin, @atl, @catalog_code,
           @purchase_unit, purchase_quantity,
           @customer_item_no, pending_msrp, pending_date, pending_net,
           promo_price, @promo_name,
           @abc_flag, @vendor, @group_code, category)";

  $r= $db->query($q)
    or die_query($db, $q);

} elseif (preg_match('/\.zip$/i', $_FILES['src']['name'])) {

  /* Update pricing from Mac */

  $q= "CREATE TEMPORARY TABLE macitem (
    item_no VARCHAR(32),
    sku VARCHAR(10),
    name VARCHAR(255),
    retail_price DECIMAL(9,2),
    promo_price DECIMAL(9,2),
    PRIMARY KEY (item_no))";

  $db->query($q)
    or die_query($db, $q);

  $base= basename($_FILES['src']['name'], '.zip');

  $q= "LOAD DATA LOCAL INFILE 'zip://$fn#$base.txt'
            INTO TABLE macitem
          FIELDS TERMINATED BY '\t'
          IGNORE 1 LINES
          (item_no, sku, name, @retail_price, @unit,
           @reno, @atlanta,
           @minimum_qty, @sale_discount_pct, @cost_factor, @promo_price)
          SET retail_price = SUBSTRING(@retail_price, 2),
              promo_price = SUBSTRING(@promo_price, 2)";

  $r= $db->query($q)
    or die_query($db, $q);

  // Find by barcode
  $q= "UPDATE vendor_item, macitem
          SET vendor_item.promo_price = macitem.promo_price
        WHERE vendor = $vendor_id AND vendor_item.code = macitem.item_no
          AND macitem.promo_price";
  $r= $db->query($q)
    or die_query($db, $q);

  echo jsonp(array("result" => "Updated " . $db->affected_rows . " items."));

  exit;

} elseif (preg_match('/^sls_sku,/', $line)) {
  $q= "CREATE TEMPORARY TABLE macitem (
    item_no VARCHAR(32),
    sku VARCHAR(10),
    name VARCHAR(255),
    retail_price DECIMAL(9,2),
    net_price DECIMAL(9,2),
    promo_price DECIMAL(9,2),
    barcode VARCHAR(32),
    purchase_quantity INT,
    category VARCHAR(64))";

  $db->query($q)
    or die_query($db, $q);

#sls_sku,cust_sku,description,vendor_name,msrp,reg_price,reg_discount,promo_price,promo_discount,upc1,upc2,upc2_qty,upc3,upc3_qty,min_ord_qty,level1,level2,level3,level4,level5,ltl_only,add_date
  $q= "LOAD DATA LOCAL INFILE '$fn'
            INTO TABLE macitem
          FIELDS TERMINATED BY ','
          OPTIONALLY ENCLOSED BY '\"'
          IGNORE 1 LINES
          (item_no, @cust_sku, name, @vendor_name,
           retail_price, net_price, @reg_discount,
           promo_price, @promo_discount,
           barcode, @upc2, @upc2_qty, @upc3, @upc3_qty,
           purchase_quantity,
           @level1, @level2, @level3, @level4, @level5,
           @ltl_only, @add_date)
        SET sku = item_no";

  $r= $db->query($q)
    or die_query($db, $q);

  // toss bad barcodes
  $q= "UPDATE macitem SET barcode = NULL WHERE LENGTH(barcode) < 3";

  $r= $db->query($q)
    or die_query($db, $q);

} elseif (preg_match('/^ma-sku/i', $_FILES['src']['name'])) {
  $q= "CREATE TEMPORARY TABLE macitem (
    item_no VARCHAR(32),
    sku VARCHAR(10),
    name VARCHAR(255),
    retail_price DECIMAL(9,2),
    net_price DECIMAL(9,2),
    promo_price DECIMAL(9,2),
    barcode VARCHAR(32),
    purchase_quantity INT,
    category VARCHAR(64))";

  $db->query($q)
    or die_query($db, $q);

  $q= "LOAD DATA LOCAL INFILE '$fn'
            INTO TABLE macitem
          FIELDS TERMINATED BY ','
          IGNORE 1 LINES
          (item_no, name, @retail_price, @l1net, net_price,
           @upp, @ppc, @weight, barcode, @wt_each,
           @length, @width, @height, purchase_quantity)
          SET retail_price = SUBSTRING(@retail_price, 2)";

  $r= $db->query($q)
    or die_query($db, $q);

} else {
  // Generic
  $q= "CREATE TEMPORARY TABLE macitem (
    item_no VARCHAR(32),
    sku VARCHAR(10),
    name VARCHAR(255),
    retail_price DECIMAL(9,2),
    net_price DECIMAL(9,2),
    promo_price DECIMAL(9,2),
    barcode VARCHAR(32),
    purchase_quantity INT,
    category VARCHAR(64))";

  $db->query($q)
    or die_query($db, $q);

  if (preg_match('/\t/', $line)) {
    $format= "FIELDS TERMINATED BY '\t'";
  } else {
    $format= "FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"'";
  }

  $q= "LOAD DATA LOCAL INFILE '$fn'
            INTO TABLE macitem
          $format
          IGNORE 1 LINES
          (item_no, sku, name, @retail_price, @net_price, @promo_price,
           barcode, purchase_quantity)
       SET
           retail_price = REPLACE(@retail_price, '$', ''),
           net_price = REPLACE(@net_price, '$', ''),
           promo_price = REPLACE(@promo_price, '$', '')";

  $r= $db->query($q)
    or die_query($db, $q);
}

$q= "DELETE FROM vendor_item WHERE vendor = $vendor_id";

$r= $db->query($q)
  or die_query($db, $q);

# ignore duplicates
$q= "INSERT IGNORE INTO vendor_item
            (vendor, item, code, vendor_sku, name,
             retail_price, net_price, promo_price,
             barcode, purchase_quantity, category)
     SELECT
            $vendor_id AS vendor,
            0 AS item,
            item_no AS code,
            sku AS vendor_sku,
            name,
            retail_price,
            net_price,
            promo_price,
            REPLACE(REPLACE(barcode, 'E-', ''), 'U-', '') AS barcode,
            purchase_quantity,
            category
       FROM macitem";

$r= $db->query($q)
  or die_query($db, $q);

$added= $db->affected_rows;

// Find by code/item_no
$q= "UPDATE vendor_item
        SET item = IFNULL((SELECT id FROM item
                            WHERE vendor_item.code = item.code),
                          0)
     WHERE vendor = $vendor_id AND item = 0";
$r= $db->query($q)
  or die_query($db, $q);

// Find by barcode
$q= "UPDATE vendor_item
        SET item = (SELECT item FROM barcode
                     WHERE barcode.code = barcode
                     LIMIT 1)
     WHERE vendor = $vendor_id AND item = 0";
$r= $db->query($q)
  or die_query($db, $q);

echo jsonp(array("result" => "Added " . $added . " items."));
