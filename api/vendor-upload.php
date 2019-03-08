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

$action= 'replace';

/* Load uploaded file into a temporary table */

# On DEBUG server, we leave behind the vendor_upload table for debugging
$temporary= "TEMPORARY";
if ($DEBUG) {
  $q= "DROP TABLE IF EXISTS vendor_upload";
  $db->query($q)
    or die_query($db, $q);
  $temporary= "";
}

$q= "CREATE $temporary TABLE vendor_upload LIKE vendor_item";
$db->query($q)
  or die_query($db, $q);

/* START Vendors */
if (preg_match('/MACITEM.*\.zip$/i', $_FILES['src']['name'])) {
  $base= basename($_FILES['src']['name'], '.zip');

  $q= "LOAD DATA LOCAL INFILE 'zip://$fn#$base.txt'
            INTO TABLE vendor_upload
          CHARACTER SET 'latin1'
          FIELDS TERMINATED BY '\t'
          IGNORE 1 LINES
          (@changed, @change_date, code, vendor_sku, name, @unit_of_sale,
           retail_price, net_price, @customer, @product_code_type,
           barcode, @reno, @elgin, @atl, @catalog_code,
           @purchase_unit, purchase_quantity,
           @customer_item_no, @pending_msrp, @pending_date, @pending_net,
           promo_price, @promo_name,
           @abc_flag, @vendor, @group_code, @catalog_description,
           weight, @cm_product_line, @cm_product_line_desc,
           @category_manager, @price_contract, @case_qty, @case_net)
        SET special_order = IF(@abc_flag = 'S', 1, 0),
            promo_quantity = purchase_quantity";

  $r= $db->query($q)
    or die_query($db, $q);

} elseif (preg_match('/^sls_sku(,|\t)/', $line, $m)) {
  // SLS
#sls_sku,cust_sku,description,vendor_name,msrp,reg_price,reg_discount,promo_price,promo_discount,upc1,upc2,upc2_qty,upc3,upc3_qty,min_ord_qty,level1,level2,level3,level4,level5,ltl_only,add_date,ormd,prop65,no_calif,no_canada,
  $q= "LOAD DATA LOCAL INFILE '$fn'
            INTO TABLE vendor_upload
          FIELDS TERMINATED BY '$m[1]'
          OPTIONALLY ENCLOSED BY '\"'
          IGNORE 1 LINES
          (code, @cust_sku, name, @vendor_name,
           retail_price, net_price, @reg_discount,
           promo_price, @promo_discount,
           barcode, @upc2, @upc2_qty, @upc3, @upc3_qty,
           purchase_quantity,
           @level1, @level2, @level3, @level4, @level5,
           @ltl_only, @add_date, @ormd, @prop65)
        SET vendor_sku = code,
            prop65 = IF(@prop65 = 'Y', 1, NULL),
            hazmat = IF(@ormd = 'Y', 1, NULL),
            oversized = IF(@ltl_only = 'Y', 1, NULL),
            promo_quantity = purchase_quantity";

  $r= $db->query($q)
    or die_query($db, $q);

} elseif (preg_match('/Alvin SRP/', $line)) {
  // Alvin Account Pricing Report
#Manufacturer	BrandName	SubBrand	AlvinItem#	Description	New	UoM	Alvin SRP	RegularMultiplier	RegularNet	CurrentMultiplier	CurrentNetPrice	CurrentPriceSource	SaleStarted	SaleExpiration	Buying Quantity (BQ)	DropShip	UPC or EAN	Weight	Length	Width	Height	Ship Truck	CountryofOrigin	HarmonizedCode	DropShipDiscount	CatalogPage	VendorItemNumber
  $sep= preg_match("/\t/", $line) ? "\t" : ",";
  $q= "LOAD DATA LOCAL INFILE '$fn'
            INTO TABLE vendor_upload
          FIELDS TERMINATED BY '$sep'
          OPTIONALLY ENCLOSED BY '\"'
          LINES TERMINATED BY '\r\n'
          IGNORE 1 LINES
          (@manufacturer, @brand, @subbrand, code,
           name, @new, @uom,
           retail_price,
           @regular_multiplier, net_price,
           @current_multiplier, promo_price, @current_price_source,
           @sale_started, @sale_ends,
           purchase_quantity,
           @dropship,
           barcode,
           weight, length, width, height, @ship_truck,
           @country_of_origin, @harmonized_code, @drop_ship_discount,
           @catalog_page, @vendor_item_number)
        SET vendor_sku = code,
            promo_quantity = purchase_quantity";

  $r= $db->query($q)
    or die_query($db, $q);

} elseif (preg_match('/C2F Pricer/', $line)) {
  // C2F Pricer
  $sep= preg_match("/\t/", $line) ? "\t" : ",";
  # Prefix,Prod,Unitstock,Descrip,Mult,Status,UPC,EAN,Retail,Net,CatDescription,Efectdt,NewRetail,NewNet
  $q= "LOAD DATA LOCAL INFILE '$fn'
            INTO TABLE vendor_upload
          FIELDS TERMINATED BY '$sep'
          OPTIONALLY ENCLOSED BY '\"'
          IGNORE 2 LINES
          (@prefix, code, @uom, name, purchase_quantity,
           @status, @upc, @ean, @msrp, @disc, @net, @category,
           @effectdt, @newretail, @newnet)
        SET vendor_sku = code,
            barcode = IF(upc, upc, ean),
            retail_price = REPLACE(REPLACE(@msrp, ',', ''), '$', ''),
            net_price = REPLACE(REPLACE(@net, ',', ''), '$', '')";

  $r= $db->query($q)
    or die_query($db, $q);

} elseif (preg_match('/^Category/', $line)) {
  // C2F promotion
  $sep= preg_match("/\t/", $line) ? "\t" : ",";

#Category	Page #	Brand	Item#	Description	SU	Sell Mult	Retail	Discount	Dealer Net	Min	New	Dropship		
  $q= "LOAD DATA LOCAL INFILE '$fn'
            INTO TABLE vendor_upload
          FIELDS TERMINATED BY '$sep'
          OPTIONALLY ENCLOSED BY '\"'
          IGNORE 1 LINES
          (@category, @page_no, @brand,
           code, name, @uom, purchase_quantity,
           retail_price, @discount,
           promo_price, @min, @new, @dropship)
        SET vendor_sku = code,
            promo_quantity = IF(@min, @min, purchase_quantity)";

  $r= $db->query($q)
    or die_query($db, $q);

  $action= "update";

} elseif (preg_match('/Golden Ratio/', $line)) {
  // Masterpiece
  $sep= preg_match("/\t/", $line) ? "\t" : ",";

#,SN,PK Sort,SKU Sort,,SKU,Golden Ratio,Size,Item Description,,,,,,,,,2016 Retail,Under $500 Net Order,Net $500 Order,Units Per Pkg,Pkgs Per Box,Weight,UPC,Freight Status,DIM. Weight,Est. Freight EACH,Est. Freight CASE
  $q= "LOAD DATA LOCAL INFILE '$fn'
            INTO TABLE vendor_upload
          FIELDS TERMINATED BY '$sep'
          OPTIONALLY ENCLOSED BY '\"'
          IGNORE 1 LINES
          (@x, @sn, @pk_sort, @sku_sort, @y,
           vendor_sku, @gr, @size, @description,
           @x1, @x2, @x3, @x4, @x5, @x6, @x7, @x8,
           @retail_price, @net_price, @promo_price,
           @units, purchase_quantity,
           weight, barcode, @freight, @dim_weight,
           @est_freight, @est_freight_case)
        SET code = CONCAT('MA', vendor_sku),
            retail_price = REPLACE(REPLACE(@retail_price, ',', ''), '$', ''),
            net_price = REPLACE(REPLACE(@net_price, ',', ''), '$', ''),
            promo_price = REPLACE(REPLACE(@promo_price, ',', ''), '$', ''),
            promo_quantity = purchase_quantity,
            name = IF(@size, CONCAT(@size, ' ', @description), @description)";

  $r= $db->query($q)
    or die_query($db, $q);

  // toss junk from header lines
  $q= "DELETE FROM vendor_upload WHERE purchase_quantity = 0";

  $r= $db->query($q)
    or die_query($db, $q);

} else {
  // Generic
  if (preg_match('/\t/', $line)) {
    $format= "FIELDS TERMINATED BY '\t'";
  } else {
    $format= "FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"'";
  }
  if (preg_match('/promo/i', $line)) {
    $action= 'promo';
  }

  $q= "LOAD DATA LOCAL INFILE '$fn'
            INTO TABLE vendor_upload
          $format
          IGNORE 1 LINES
          (code, vendor_sku, name, @retail_price, @net_price, @promo_price,
           @barcode, @purchase_quantity, @promo_quantity)
       SET
           retail_price = REPLACE(REPLACE(@retail_price, ',', ''), '$', ''),
           net_price = REPLACE(REPLACE(@net_price, ',', ''), '$', ''),
           promo_price = REPLACE(REPLACE(@promo_price, ',', ''), '$', ''),
           barcode = REPLACE(REPLACE(@barcode, '-', ''), ' ', ''),
           purchase_quantity = IF(@purchase_quantity, @purchase_quantity, 1),
           promo_quantity = IF(@promo_quantity, @promo_quantity,
                               IF(@promo_price, purchase_quantity, NULL))";

  $r= $db->query($q)
    or die_query($db, $q);
}
/* END Vendors */

/* Just toss bad barcodes to avoid grief */
$q= "UPDATE vendor_upload SET barcode = NULL WHERE LENGTH(barcode) < 3";
$db->query($q)
  or die_query($db, $q);

/* If we are replacing vendor data, mark the old stuff inactive */
if ($action == 'replace') {
  $q= "UPDATE vendor_item SET active = 0 WHERE vendor = $vendor_id";
  $db->query($q)
    or die_query($db, $q);
} 
/* If this is a promo, unset existing promos for this vendor */
if ($action == 'promo') {
  $q= "UPDATE vendor_item SET promo_price = NULL, promo_quantity = NULL
        WHERE vendor = $vendor_id";
  $db->query($q)
    or die_query($db, $q);
}

$q= "INSERT INTO vendor_item
            (vendor, item, code, vendor_sku, name,
             retail_price, net_price, promo_price, promo_quantity,
             barcode, purchase_quantity, 
             length, width, height, weight,
             prop65, hazmat, oversized,
             special_order)
     SELECT
            $vendor_id AS vendor,
            0 AS item,
            code,
            vendor_sku,
            name,
            retail_price,
            net_price,
            promo_price,
            promo_quantity,
            REPLACE(REPLACE(barcode, 'E-', ''), 'U-', '') AS barcode,
            purchase_quantity,
            length, width, height, weight,
            prop65, hazmat, oversized,
            special_order
       FROM vendor_upload
     ON DUPLICATE KEY UPDATE
       code = VALUES(code),
       vendor_sku = VALUES(vendor_sku),
       name = VALUES(name),
       retail_price = IF(VALUES(retail_price),
                         VALUES(retail_price), vendor_item.retail_price),
       net_price = IF(VALUES(net_price),
                      VALUES(net_price), vendor_item.net_price),
       promo_price = VALUES(promo_price),
       promo_quantity = VALUES(promo_quantity),
       barcode = IF(VALUES(barcode) != '',
                    VALUES(barcode), vendor_item.barcode),
       purchase_quantity = IF(VALUES(purchase_quantity),
                              VALUES(purchase_quantity),
                              vendor_item.purchase_quantity),
       length = IFNULL(VALUES(length),
                       vendor_item.length),
       width = IFNULL(VALUES(width),
                      vendor_item.width),
       height = IFNULL(VALUES(height),
                       vendor_item.height),
       weight = IFNULL(VALUES(weight),
                       vendor_item.weight),
       prop65 = IFNULL(VALUES(prop65),
                       vendor_item.prop65),
       hazmat = IFNULL(VALUES(hazmat),
                       vendor_item.hazmat),
       oversized = IFNULL(VALUES(oversized),
                          vendor_item.oversized),
       special_order = IFNULL(VALUES(special_order),
                              vendor_item.special_order),
       active = 1
     ";

$r= $db->query($q)
  or die_query($db, $q);

$added_or_updated= $db->affected_rows;

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
     WHERE vendor = $vendor_id
       AND item = 0
       AND barcode IS NOT NULL
       AND barcode != ''";
$r= $db->query($q)
  or die_query($db, $q);

echo jsonp(array("result" =>
                 "Added or updated " . $added_or_updated . " items."));
