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
    abc_flag CHAR(3),
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
           abc_flag, @vendor, @group_code, category)";

  $r= $db->query($q)
    or die_query($db, $q);

} elseif (preg_match('/^sls_sku,/', $line)) {
  // SLS
  //
  $q= "CREATE TEMPORARY TABLE macitem (
    item_no VARCHAR(32),
    sku VARCHAR(10),
    name VARCHAR(255),
    retail_price DECIMAL(9,2),
    net_price DECIMAL(9,2),
    promo_price DECIMAL(9,2),
    barcode VARCHAR(32),
    purchase_quantity INT,
    abc_flag CHAR(3),
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

} elseif (preg_match('/Alvin SRP/', $line)) {
  // Alvin Account Pricing Report
  //
  $q= "CREATE TEMPORARY TABLE macitem (
    item_no VARCHAR(32),
    sku VARCHAR(10),
    name VARCHAR(255),
    retail_price DECIMAL(9,2),
    net_price DECIMAL(9,2),
    promo_price DECIMAL(9,2),
    barcode VARCHAR(32),
    purchase_quantity INT,
    abc_flag CHAR(3),
    category VARCHAR(64))";

  $db->query($q)
    or die_query($db, $q);

#Manufacturer	BrandName	SubBrand	AlvinItem#	Description	New	UoM	Alvin SRP	RegularMultiplier	RegularNet	CurrentMultiplier	CurrentNetPrice	CurrentPriceSource	SaleStarted	SaleExpiration	Buying Quantity (BQ)	DropShip	UPC or EAN	Weight	Length	Width	Height	Ship Truck	CountryofOrigin	HarmonizedCode	DropShipDiscount	CatalogPage	VendorItemNumber
  $q= "LOAD DATA LOCAL INFILE '$fn'
            INTO TABLE macitem
          FIELDS TERMINATED BY '\t'
          OPTIONALLY ENCLOSED BY '\"'
          IGNORE 1 LINES
          (@manufacturer, @brand, @subbrand, item_no,
           name, @new, @uom,
           retail_price,
           @regular_multiplier, net_price,
           @current_multiplier, promo_price, @current_price_source,
           @sale_started, @sale_ends,
           purchase_quantity,
           @dropship,
           barcode,
           @weight, @length, @width, @height, @ship_truck,
           @country_of_origin, @harmonized_code, @drop_ship_discount,
           @catalog_page, @vendor_item_number)
        SET sku = item_no";

  $r= $db->query($q)
    or die_query($db, $q);

  // toss bad barcodes
  $q= "UPDATE macitem SET barcode = NULL WHERE LENGTH(barcode) < 3";

  $r= $db->query($q)
    or die_query($db, $q);


} elseif (preg_match('/C2F Pricer/', $line)) {
  // C2F Pricer
  //
  $q= "CREATE TEMPORARY TABLE macitem (
    item_no VARCHAR(32),
    sku VARCHAR(10),
    name VARCHAR(255),
    retail_price DECIMAL(9,2),
    net_price DECIMAL(9,2),
    promo_price DECIMAL(9,2),
    barcode VARCHAR(32),
    purchase_quantity INT,
    abc_flag CHAR(3),
    category VARCHAR(64))";

  $db->query($q)
    or die_query($db, $q);

#Cat Desc,Prefix,Prod,Descrip,Unitstock,Mult,Status,Nonstockty,UPC,EAN,Effectdt,NewRetail,EffPrice1,EffQtyPrice,Retail,DealerNet,Qtybrk,QtyPrice,CaseQty,CasePrice 
  $q= "LOAD DATA LOCAL INFILE '$fn'
            INTO TABLE macitem
          FIELDS TERMINATED BY ','
          OPTIONALLY ENCLOSED BY '\"'
          IGNORE 3 LINES
          (@category, @prefix, item_no, name,
           @uom, purchase_quantity, @status, @nonstockty,
           @upc, @ean, @effectdt, @newretail, @effprice1, @effqtyprice,
           retail_price, net_price, @qty_brk, @qty_price, @case_qty,
           @case_price)
        SET sku = item_no, barcode= IF(@upc != '', @upc, @ean)";

  $r= $db->query($q)
    or die_query($db, $q);

  // toss bad barcodes
  $q= "UPDATE macitem SET barcode = NULL WHERE LENGTH(barcode) < 3";

  $r= $db->query($q)
    or die_query($db, $q);


} elseif (preg_match('/Golden Ratio/', $line)) {
  // Masterpiece
  //
  $q= "CREATE TEMPORARY TABLE macitem (
    item_no VARCHAR(32),
    sku VARCHAR(10),
    name VARCHAR(255),
    retail_price DECIMAL(9,2),
    net_price DECIMAL(9,2),
    promo_price DECIMAL(9,2),
    barcode VARCHAR(32),
    purchase_quantity INT,
    abc_flag CHAR(3),
    category VARCHAR(64))";

  $db->query($q)
    or die_query($db, $q);

#,SN,PK Sort,SKU Sort,,SKU,Golden Ratio,Size,Item Description,,,,,,,,,2016 Retail,Under $500 Net Order,Net $500 Order,Units Per Pkg,Pkgs Per Box,Weight,UPC,Freight Status,DIM. Weight,Est. Freight EACH,Est. Freight CASE
  $q= "LOAD DATA LOCAL INFILE '$fn'
            INTO TABLE macitem
          FIELDS TERMINATED BY ','
          OPTIONALLY ENCLOSED BY '\"'
          IGNORE 1 LINES
          (@x, @sn, @pk_sort, @sku_sort, @y,
           sku, @gr, @size, @description,
           @x1, @x2, @x3, @x4, @x5, @x6, @x7, @x8,
           @retail_price, @net_price, @promo_price,
           @units, purchase_quantity,
           @weight, barcode, @freight, @dim_weight,
           @est_freight, @est_freight_case)
        SET item_no = CONCAT('MA', sku),
            retail_price = REPLACE(REPLACE(@retail_price, ',', ''), '$', ''),
            net_price = REPLACE(REPLACE(@net_price, ',', ''), '$', ''),
            promo_price = REPLACE(REPLACE(@promo_price, ',', ''), '$', ''),
            name = IF(@size, CONCAT(@size, ' ', @description), @description)";

  $r= $db->query($q)
    or die_query($db, $q);

  // toss bad barcodes
  $q= "UPDATE macitem SET barcode = NULL WHERE LENGTH(barcode) < 3";

  $r= $db->query($q)
    or die_query($db, $q);

  // toss junk from header lines
  $q= "DELETE FROM macitem WHERE purchase_quantity = 0";

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
    abc_flag CHAR(3),
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
           @barcode, purchase_quantity)
       SET
           retail_price = REPLACE(REPLACE(@retail_price, ',', ''), '$', ''),
           net_price = REPLACE(REPLACE(@net_price, ',', ''), '$', ''),
           promo_price = REPLACE(REPLACE(@promo_price, ',', ''), '$', ''),
           barcode = REPLACE(@barcode, '-', '')";

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
             barcode, purchase_quantity, category, special_order)
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
            category,
            IF(abc_flag = 'S', 1, 0) AS special_order
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
        SET item = IFNULL((SELECT item FROM barcode
                            WHERE barcode.code = barcode
                            LIMIT 1),
                          0)
     WHERE vendor = $vendor_id AND item = 0";
$r= $db->query($q)
  or die_query($db, $q);

echo jsonp(array("result" => "Added " . $added . " items."));
