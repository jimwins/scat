<?php
namespace Scat\Service;

class VendorData
{
  public function __construct(
    private Config $config,
    private Data $data
  ) {
  }

  public function loadVendorItems(\Scat\Model\Person $person, $file) {
    $fn= $file->getClientFilename();
    $stream= $file->getStream();
    $tmpfn= ($stream->getMetaData())['uri'];

    /* Grab the first line for detecting file type */
    $line= $stream->read(1024);
    if (($nl= strpos($line, "\n"))) {
      $line= substr($line, 0, $nl);
    }
    $stream->close();

    ob_start();

    $action= 'replace';

    // TODO should throw better exceptions throughout

    /* Load uploaded file into a temporary table */
    $temporary= "TEMPORARY";
    /* With DEBUG, we leave behind the vendor_upload table for debugging */
    if ($GLOBALS['DEBUG']) {
      $q= "DROP TABLE IF EXISTS vendor_upload";
      if (!$this->data->execute($q))
        throw new \Exception("Unable to drop 'vendor_upload'");
      $temporary= "";
    }

    $q= "CREATE $temporary TABLE vendor_upload LIKE vendor_item";
    if (!$this->data->execute($q))
      throw new \Exception("Unable to create 'vendor_upload'");

    /* START Vendors */
    if (preg_match('/MACITEM.*\.zip$/i', $fn)) {
      error_log("Importing '$fn' as Mac price list\n");
      $base= basename($fn, '.zip');

      $q= "LOAD DATA LOCAL INFILE 'zip://$tmpfn#$base.txt'
                INTO TABLE vendor_upload
              CHARACTER SET 'latin1'
              FIELDS TERMINATED BY '\t'
              IGNORE 1 LINES
              (@changed, @change_date, code, @vendor_sku, name, @unit_of_sale,
               retail_price, net_price, @customer, @product_code_type,
               barcode, @reno, @elgin, @atl, @catalog_code,
               @purchase_unit, purchase_quantity,
               @customer_item_no, @pending_msrp, @pending_date, @pending_net,
               promo_price, @promo_name,
               @abc_flag, @vendor, @group_code, @catalog_description,
               weight, @cm_product_line, @cm_product_line_desc,
               @category_manager, @price_contract, @case_qty, @case_net)
            SET vendor_sku = code, special_order = IF(@reno != 'R', 1, 0),
                promo_quantity = purchase_quantity";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load Mac data file");

    } elseif (preg_match('/^Mac Item #(,|\t)/', $line, $m)) {
      // Mac Catalog data

      error_log("Importing '$fn' as Mac catalog data\n");
      $action= 'update';

      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
                INTO TABLE vendor_upload
              FIELDS TERMINATED BY '$m[1]'
              OPTIONALLY ENCLOSED BY '\"'
              IGNORE 1 LINES
              (code, @product_id, @ean, @upc, @brand,
               name,
               @title_general, @short_description, @long_copy,
               @bullet_1, @bullet_2, @bullet_3, @bullet_4, @bullet_5,
               @bullet_6, @bullet_7,
               @color, @color_family,
               @shape, @size, @subfamily_description, @key_features_list,
               @key_features_list_2, @key_features_list_3, @keywords,
               @catalog_code_description, @item_category,
               @ecplus20, @ecplus, @future_msrp, @map,
               height, length, weight, width,
               @state_restrictions, @has_state_restriction,
               @prop65, @prop65_label, @voc, @hazmat_flag,
               @web_item_description, @ormd,
               @lots_more)
            SET vendor_sku = code,
                barcode = IF(@ean, @ean, @upc),
                prop65 = IF(@prop65 = 'Yes', 1, NULL),
                hazmat = IF(@ormd = 'ORMD', 1, NULL)";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load Mac Catalog file");

    } elseif (preg_match('/^"?sls_sku"?(,|\t)/', $line, $m)) {
      // SLS
#sls_sku,cust_sku,description,vendor_name,msrp,reg_price,reg_discount,promo_price,promo_discount,upc1,upc2,upc2_qty,upc3,upc3_qty,min_ord_qty,level1,level2,level3,level4,level5,ltl_only,add_date,ormd,prop65,no_calif,no_canada,stand_map,st_map_sel,promo_map,pr_map_sel,country,qoh_no,qoh_vegas

      error_log("Importing '$fn' as SLS price list\n");

      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
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
                barcode = IF(barcode != '', barcode, @upc2),
                prop65 = IF(@prop65 = 'Y', 1, NULL),
                hazmat = IF(@ormd = 'Y', 1, NULL),
                oversized = IF(@ltl_only = 'Y', 1, NULL),
                special_order = IF(@level1 = 'DROP-SHIP ONLY PRODUCTS', 1,
                                   IF(@level1 = 'ASSORTMENTS AND DISPLAYS', 2, 0)),
                promo_quantity = purchase_quantity";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load SLS data file");

      // delete assortments
      $q= "DELETE FROM vendor_upload WHERE special_order = 2";
      if (!$this->data->execute($q))
        throw new \Exception("Unable to delete assortments from SLS data");

    } elseif (preg_match('/^ColArt/', $line, $m)) {
      $sep= preg_match("/\t/", $line) ? "\t" : ",";
      // ColArt
#,Order,Brand,Category,Range,Size/Format,Product Code,Description,Product Notes,Health Label,Series,Bar Code,Inner Pack Bar Code,Case Pack Bar Code,MOQ,Inner Pack,Case Pack,Trade Discount,,Promo Discount,2020 MSRP,Net,Extended Net,USA MAP Pricing,Country of Origin,Harmonized Tariff Codes,Height (Inches),Width (Inches),Depth (Inches),Cubic Feet,Weight (Oz),Height (Inches),Width (Inches),Depth (Inches),Cubic Feet,Weight (Oz),Height (Inches),Width (Inches),Depth (Inches),Cubic Feet,Weight (Oz),,,,,,,,,,,,,,
      error_log("Importing '$fn' as ColArt price list\n");
      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
                INTO TABLE vendor_upload
              FIELDS TERMINATED BY '$sep'
              OPTIONALLY ENCLOSED BY '\"'
               LINES TERMINATED BY '\r\n'
              IGNORE 1 LINES
              (@a, @order, @brand, @category, @range, @size,
               vendor_sku, name, @notes, @health_label, @series,
               barcode, @inner_pack_upc, @case_pack_upc,
               purchase_quantity, @inner_pack, @case_pack,
               @trade_discount, @blank, @promo_discount,
               retail_price, net_price, @extended_net, @map_price,
               @country_of_origin,
               @tariff, height, width, length, @cubic, weight)
              SET code = CONCAT(CASE @brand
                                WHEN 'WINSOR & NEWTON' THEN 'WN'
                                WHEN 'CONTE A PARIS' THEN 'CO'
                                WHEN 'EDDING' THEN 'EDD-'
                                WHEN 'LIQUITEX' THEN 'LQ'
                                WHEN 'LEFRANC & BOURGEOIS' THEN 'LB'
                                WHEN 'SCULPTURE BLOCK' THEN 'SL'
                                WHEN 'SNAZAROO' THEN 'SN'
                                WHEN 'VIVIVA' THEN 'VV'
                                WHEN 'WINSOR & NEWTON' THEN 'WN'
                                ELSE 'COL-' END, vendor_sku),
                  weight = weight / 16,
                  prop65 = IF(@health_label = '65', 1, 0)
              ";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load ColArt data file");

      // toss junk from header lines
      $q= "DELETE FROM vendor_upload WHERE purchase_quantity = 0";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load ColArt data file");

    } elseif (preg_match('/^colart-promo/', $line, $m)) {
      // ColArt Update
      $action= 'promo';
      $sep= preg_match("/\t/", $line) ? "\t" : ",";

#,Order,Product Code,Description,Health Label,Series,Bar Code,MOQ,Inner Pack,Case Pack,Trade Discount,Promo Discount,MSRP,Net,Extended Net,USA MAP Everyday Pricing,USA MAP Promo Pricing,Harmonized Tariff Codes,Height (Inches),Width (Inches),Depth (Inches),Cubic Feet,Weight (Oz),Height (Inches),Width (Inches),Depth (Inches),Cubic Feet,Weight (Oz),Height (Inches),Width (Inches),Depth (Inches),Cubic Feet,Weight (Oz),,,,,,,,,,,,,,
      error_log("Importing '$fn' as ColArt promo price list\n");
      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
                INTO TABLE vendor_upload
              FIELDS TERMINATED BY '$sep'
              OPTIONALLY ENCLOSED BY '\"'
               LINES TERMINATED BY '\r\n'
              IGNORE 1 LINES
              (@a, @order, vendor_sku, name, @health_label, @series,
               barcode, promo_quantity,
               @inner_pack, @case_pack, @trade_discount, @promo_discount,
               retail_price, promo_price)";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load ColArt data file");

      // toss junk from header lines
      $q= "DELETE FROM vendor_upload WHERE promo_quantity = 0";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load ColArt data file");

    } elseif (preg_match('/masterpiece/', $line)) {
      // Masterpiece
      error_log("Importing '$fn' as Masterpiece price list\n");
      $sep= preg_match("/\t/", $line) ? "\t" : ",";

#,SN,PK Sort,SKU Sort,,SKU,NEW SKU,Golden Ratio,Size,Item Description,,,,,,,,,2020 Retail,Under $500 Net Order,Net $500 Order,Units Per Pkg,Pkgs Per Box,Weight,UPC,Freight Status,DIM. Weight,Est. Freight EACH,Est. Freight CASE,Item length,Item Width,Item Height,Carton Length,Carton Width,Carton Height,HTS,Origin,

      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
                INTO TABLE vendor_upload
              FIELDS TERMINATED BY '$sep'
              OPTIONALLY ENCLOSED BY '\"'
              IGNORE 3 LINES
              (@x, @sn, @pk_sort, @sku_sort, @y,
               @sku, @new_sku, @gr, @size, @description,
               @x1, @x2, @x3, @x4, @x5, @x6, @x7, @x8,
               @retail_price, @net_price, @promo_price,
               @units, purchase_quantity,
               @weight, barcode, @freight, @dim_weight,
               @est_freight, @est_freight_case,
               length, width, height,
               @carton_length, @carton_width, @carton_height, @hts, @origin)
            SET vendor_sku = IF(@new_sku != '' AND @new_sku != '0', @new_sku, @sku),
                code = CONCAT('MA', vendor_sku),
                weight = @weight / purchase_quantity,
                oversized = IF(@freight = 'OS' OR @freight = 'LTL', 1, 0),
                retail_price = REPLACE(REPLACE(@retail_price, ',', ''), '$', ''),
                net_price = REPLACE(REPLACE(@net_price, ',', ''), '$', ''),
                promo_price = REPLACE(REPLACE(@promo_price, ',', ''), '$', ''),
                promo_quantity = purchase_quantity,
                name = IF(@size, CONCAT(@size, ' ', @description), @description)";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load Masterpiece data file");
      // toss junk from header lines
      $q= "DELETE FROM vendor_upload WHERE purchase_quantity = 0";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load Masterpiece data file");

    } elseif (preg_match('/^ItemNum/', $line)) {
      /* PA Dist */
#ItemNum	UM	SMIN	STD	CustomerPrice	List	Retail	ItemDesc	UPC
      error_log("Importing '$fn' as PA Distribution price list\n");
      $sep= preg_match("/\t/", $line) ? "\t" : ",";
      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
                INTO TABLE vendor_upload
              FIELDS TERMINATED BY '$sep'
              OPTIONALLY ENCLOSED BY '\"'
               LINES TERMINATED BY '\r\n'
              IGNORE 1 LINES
              (vendor_sku, @uom, purchase_quantity, @std,
              @net_price, @x, @retail_price,
              name,
              barcode)
              SET code = vendor_sku,
                  retail_price = REPLACE(REPLACE(@retail_price, ',', ''), '$', ''),
                  net_price = REPLACE(REPLACE(@net_price, ',', ''), '$', '')
              ";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load PA Dist data file");

      // toss junk from header lines
      $q= "DELETE FROM vendor_upload WHERE purchase_quantity = 0";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load PA Dist data file");
    } elseif (preg_match('/^Brand[,\t]/', $line)) {
      /* PA Dist */
#Brand	Qty	ItemNum	UM	SMIN	CustomerPrice	Retail	ItemDesc	UPC
      error_log("Importing '$fn' as PA Distribution price list\n");
      $sep= preg_match("/\t/", $line) ? "\t" : ",";
      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
                INTO TABLE vendor_upload
              FIELDS TERMINATED BY '$sep'
              OPTIONALLY ENCLOSED BY '\"'
               LINES TERMINATED BY '\r\n'
              IGNORE 1 LINES
              (@brand, @qty, vendor_sku, @uom, purchase_quantity,
              @net_price, @retail_price, name, @barcode)
              SET code = vendor_sku,
                  barcode = IF(@barcode != 'N/A', @barcode, ''),
                  retail_price = REPLACE(REPLACE(@retail_price, ',', ''), '$', ''),
                  net_price = REPLACE(REPLACE(@net_price, ',', ''), '$', '')
              ";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load PA Dist data file");

      // toss junk from header lines
      $q= "DELETE FROM vendor_upload WHERE purchase_quantity = 0";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load PA Dist data file");
    } elseif (preg_match('/^SKU[\t,]UPC/', $line, $m)) {
      $sep= preg_match("/\t/", $line) ? "\t" : ",";
      // Notions
#SKU	UPC	Retail Price	Wholesale Price	Quantity Available	Description	Brand	Minimum Sell	Case Pack	Freight Collect
      error_log("Importing '$fn' as Notions price list\n");
      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
                INTO TABLE vendor_upload
              FIELDS TERMINATED BY '$sep'
              OPTIONALLY ENCLOSED BY '\"'
               LINES TERMINATED BY '\r\n'
              IGNORE 1 LINES
              (vendor_sku, barcode, @retail_price, @net_price, @stock,
               name, @brand, purchase_quantity, @case_pack, @freight_collect)
              SET code = vendor_sku,
                  retail_price = REPLACE(REPLACE(@retail_price, ',', ''), '$', ''),
                  net_price = REPLACE(REPLACE(@net_price, ',', ''), '$', '')
              ";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load Notions data file");

      // toss junk from header lines
      $q= "DELETE FROM vendor_upload WHERE purchase_quantity = 0";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load Notions data file");

    } elseif (preg_match('/^golden/', $line)) {
      /* Golden */
      error_log("Importing '$fn' as Golden price list\n");
      $sep= preg_match("/\t/", $line) ? "\t" : ",";
      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
                INTO TABLE vendor_upload
              FIELDS TERMINATED BY '$sep'
              OPTIONALLY ENCLOSED BY '\"'
               LINES TERMINATED BY '\n'
              IGNORE 1 LINES
              (vendor_sku, @description, @size, @series, purchase_quantity,
               barcode, @retail_price)
              SET code = IF(vendor_sku LIKE '0000%',
                            CONCAT('GD', MID(vendor_sku, 4, 100)),
                            IF(vendor_sku LIKE '6%',
                                CONCAT('WB', vendor_sku),
                                CONCAT('QR', vendor_sku))),
                  retail_price = REPLACE(REPLACE(@retail_price, ',', ''), '$', ''),
                  net_price = retail_price * 0.41
              ";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load PA Dist data file");

      // toss junk from header lines
      $q= "DELETE FROM vendor_upload WHERE purchase_quantity = 0";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load PA Dist data file");
    } else {
      // Generic
      if (preg_match("/\\t/", $line)) {
        $fmt= 'TSV';
        $format= "FIELDS TERMINATED BY '\t' OPTIONALLY ENCLOSED BY '\"'";
      } else {
        $fmt= 'CSV';
        $format= "FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"'";
      }
      error_log("Importing '$fn' as generic $fmt price list\n");
      if (preg_match('/promo/i', $line)) {
        $action= 'promo';
      }
      if (preg_match('/extend/i', $line)) {
        $action= 'extend';
      }

      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
                INTO TABLE vendor_upload
              $format
              IGNORE 1 LINES
              (@code, @vendor_sku, @name,
               @retail_price, @net_price,
               @barcode, @purchase_quantity,
               weight, length, width, height)
           SET
               code = TRIM(@code),
               vendor_sku = TRIM(@vendor_sku),
               name = TRIM(@name),
               retail_price = REPLACE(REPLACE(@retail_price, ',', ''), '$', ''),
               net_price = REPLACE(REPLACE(@net_price, ',', ''), '$', ''),
               barcode = REPLACE(REPLACE(@barcode, '-', ''), ' ', ''),
               purchase_quantity = IF(@purchase_quantity, @purchase_quantity, 1)";

      if (!$this->data->execute($q))
        throw new \Exception("Unable to load generic data file");
    }
    /* END Vendors */

    /* Just toss bad barcodes to avoid grief */
    $q= "UPDATE vendor_upload SET barcode = NULL WHERE LENGTH(barcode) < 3";
    if (!$this->data->execute($q))
      throw new \Exception("Unable to toss bad barcodes");

    /* If we are replacing vendor data, mark the old stuff inactive */
    if ($action == 'replace') {
      $q= "UPDATE vendor_item SET active = 0 WHERE vendor_id = {$person->id}";
      if (!$this->data->execute($q))
        throw new \Exception("Unable to deactive old items");
    }
    /* If this is a promo, unset existing promos for this vendor */
    if ($action == 'promo') {
      $q= "UPDATE vendor_item SET promo_price = NULL, promo_quantity = NULL
            WHERE vendor_id = {$person->id}";
      if (!$this->data->execute($q))
        throw new \Exception("Unable to clear old promos");
    }

    $q= "INSERT INTO vendor_item
                (vendor_id, item_id, code, vendor_sku, name,
                 retail_price, net_price, promo_price, promo_quantity,
                 barcode, purchase_quantity,
                 length, width, height, weight,
                 prop65, hazmat, oversized,
                 special_order)
         SELECT
                {$person->id} AS vendor_id,
                0 AS item_id,
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
           code = IF(VALUES(code) != '', VALUES(code), vendor_item.code),
           vendor_sku = VALUES(vendor_sku),
           name = IF(VALUES(name) != '', VALUES(NAME), vendor_item.name),
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

    if (!$this->data->execute($q))
      throw new \Exception("Unable to add and update items");

    $added_or_updated= $this->data->get_last_statement()->rowCount();

    // Find by code/item_no
    $q= "UPDATE vendor_item
            SET item_id = IFNULL((SELECT id FROM item
                                   WHERE vendor_item.code = item.code
                                     AND item.active),
                              0)
         WHERE vendor_id = {$person->id}
           AND (item_id IS NULL OR item_id = 0)";
    if (!$this->data->execute($q))
      throw new \Exception("Unable to match items by code");

    // Find by barcode
    $q= "UPDATE vendor_item
            SET item_id = IFNULL((SELECT item_id
                                    FROM barcode
                                    JOIN item ON item.id = barcode.item_id
                                   WHERE barcode.code = barcode
                                     AND item.active
                                   LIMIT 1),
                              0)
         WHERE vendor_id = {$person->id}
           AND (item_id IS NULL OR item_id = 0)
           AND barcode IS NOT NULL
           AND barcode != ''";
    if (!$this->data->execute($q))
      throw new \Exception("Unable to match items by barcode");

    return [ "message" => "Added or updated " . $added_or_updated . " items." ];
  }

  public function loadVendorOrder(\Scat\Model\Txn $txn, $file) {
    $fn= $file->getClientFilename();
    $stream= $file->getStream();
    $tmpfn= ($stream->getMetaData())['uri'];

    /* Grab the first line for detecting file type */
    $line= $stream->read(1024);
    $stream->close();

    $temporary= "TEMPORARY";
    // If DEBUG, we leave behind the vendor_order table
    if ($GLOBALS['DEBUG']) {
      $this->data->execute("DROP TABLE IF EXISTS vendor_order");
      $temporary= "";
    }

    $update_only= false;

    error_log("Loading order data from '$fn'\n");

    $q= "CREATE $temporary TABLE vendor_order (
           line int,
           status varchar(255),
           item_no varchar(255),
           item_id int unsigned,
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
           box_no varchar(255),
           key (item_id), key(item_no), key(sku))";

    $this->data->execute($q);

    // SLS order?
    if (preg_match('/^"?linenum"?[,\t]"?qty/', $line)) {
      error_log("Loading SLS text data\n");
      // linenum,qty_shipped,sls_sku,cust_item_numb,description,upc,msrp,net_cost,pkg_id,extended_cost
      $sep= preg_match("/,/", $line) ? "," : "\t";
      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
           INTO TABLE vendor_order
           FIELDS TERMINATED BY '$sep' OPTIONALLY ENCLOSED BY '\"'
           IGNORE 1 LINES
           (line, @shipped, item_no, cust_item, description, @upc,
            msrp, net, box_no, ext)
           SET barcode = REPLACE(@upc, 'UPC->', ''),
               sku = item_no,
               ordered = @shipped, backordered = @shipped, shipped = 0";
      $this->data->execute($q);

    // SLS order (XLS)
    } elseif (preg_match('/K.*\\.xls/i', $fn)) {
      error_log("Loading SLS Excel data\n");
      $reader= new \PhpOffice\PhpSpreadsheet\Reader\Xls();
      $reader->setReadDataOnly(true);

      $spreadsheet= $reader->load($tmpfn);
      $sheet= $spreadsheet->getActiveSheet();
      $i= 0; $rows= [];
      foreach ($sheet->getRowIterator() as $row) {
        if ($i++) {
          $data= [];
          $cellIterator= $row->getCellIterator();
          $cellIterator->setIterateOnlyExistingCells(false);
          foreach ($cellIterator as $cell) {
            $data[]= $this->data->escape($cell->getValue());
          }
          $rows[]= '(' . join(',', $data) . ')';
        }
      }
      $q= "INSERT INTO vendor_order (line, ordered, item_no, cust_item, description, barcode, msrp, net, box_no, ext, bo_no) VALUES " . join(',', $rows);
      $this->data->execute($q);

      $q= "UPDATE vendor_order SET backordered = ordered, shipped = 0";
      $this->data->execute($q);

    } elseif (preg_match('/^Vendor Name	Assortment Item Number/', $line)) {
      error_log("Loading Mac assortment data\n");
      // MacPherson assortment
      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
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
      $this->data->execute($q);

    } elseif (preg_match('/^"?sls_sku.*asst_qty/', $line)) {
      error_log("Loading SLS assortment data\n");
      // SLS assortment
      # sls_sku,cust_sku,description,vendor_name,msrp,reg_price,reg_discount,promo_price,promo_discount,upc1,upc2,upc2_qty,upc3,upc3_qty,min_ord_qty,level1,level2,level3,level4,level5,ltl_only,add_date,asst_qty,
      $sep= preg_match("/,/", $line) ? "," : "\t";
      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
           INTO TABLE vendor_order
           FIELDS TERMINATED BY '$sep'
           OPTIONALLY ENCLOSED BY '\"'
           LINES TERMINATED BY '\n'
           IGNORE 1 LINES
           (item_no, @cust_sku, description, @vendor_name,
            msrp, net, @reg_discount, @promo_price, @promo_discount,
            barcode, @upc2, @upc2_qty, @upc3, @upc3_qty,
            @min_ord_qty, @level2, @level2, @level3, @level4, @level5,
            @ltl_only, @add_date, @asst_qty)
           SET ordered = @asst_qty, shipped = @asst_qty";
      $this->data->execute($q);

    } elseif (preg_match('/orderdetails\.csv/', $fn)) {
      error_log("Loading Notions Marketing\n");
      $sep= preg_match("/,/", $line) ? "," : "\t";
      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
           INTO TABLE vendor_order
           FIELDS TERMINATED BY '$sep'
           OPTIONALLY ENCLOSED BY '\"'
           LINES TERMINATED BY '\n'
           (@code, item_no, barcode, description, @x, @brand, @ordered, ordered, @z,
            @msrp, @net, @ext, @box_no)
           SET msrp = REPLACE(@msrp, '$', ''), net = REPLACE(@net, '$', ''),
               backordered = ordered, shipped = 0";
      $this->data->execute($q);

    } elseif (preg_match('/^,Name,MSRP/', $line)) {
      error_log("Loading CSV data\n");
      // CSV
      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
           INTO TABLE vendor_order
           FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"'
           IGNORE 1 LINES
           (item_no, description, @msrp, @sale, @net, @qty, @ext, barcode)
           SET ordered = @qty, shipped = @qty,
               msrp = REPLACE(@msrp, '$', ''), net = REPLACE(@net, '$', '')";
      $this->data->execute($q);

    } elseif (preg_match('/^Invoice#;/', $line)) {
      error_log("Loading ColArt data\n");
      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
           INTO TABLE vendor_order
           FIELDS TERMINATED BY ';'
           LINES TERMINATED BY '\r\n'
           IGNORE 1 LINES
           (@invoice_no, @order_no, @order_line, @upc, @code, @alias,
            @country_of_origin, @customs_code, description, @qty, msrp,
            @discount_pct, net, @ext, @currency)
           SET item_no= @code, barcode= REPLACE(@upc, \"'\", ''),
               ordered = @qty, shipped = @qty";
      $this->data->execute($q);

      $update_only= true;

    } elseif (preg_match('/^code\tqty/', $line)) {
      error_log("Loading order file\n");
      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
           INTO TABLE vendor_order
           FIELDS TERMINATED BY '\t' OPTIONALLY ENCLOSED BY '\"'
           IGNORE 1 LINES
           (item_no, @qty)
           SET sku = item_no, ordered = @qty, shipped = @qty";
      $this->data->execute($q);

    } elseif (($json= json_decode(file_get_contents($tmpfn)))) {
      error_log("Loading JSON order\n");
      foreach ($json->items as $item) {
        $q= "INSERT INTO vendor_order
                SET item_no = '" . $this->data->escape($item->code) . "',
                    description = '" . $this->data->escape($item->name) . "',
                    ordered = -" . (int)$item->quantity . ",
                    shipped = -" . (int)$item->quantity . ",
                    msrp = '" . $this->data->escape($item->retail_price) . "',
                    net = '" . $this->data->escape($item->sale_price) . "'";
        $this->data->execute($q);
      }

    } else {
      error_log("Loading Mac text data\n");
      // MacPherson's order
      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
           INTO TABLE vendor_order
           CHARACTER SET 'latin1'
           FIELDS TERMINATED BY '\t' OPTIONALLY ENCLOSED BY '\"'
           IGNORE 1 LINES
           (line, status, item_no, sku, cust_item, description, ordered,
            @shipped, backordered, msrp, discount, net, unit, ext, barcode,
            account_no, po_no, order_no, bo_no, invoice_no, box_no)
           SET
              shipped = 0,
              ordered = IF(@shipped > 0, @shipped, ordered),
              backordered = IF(@shipped > 0, @shipped + backordered, backordered)
           ";
      $this->data->execute($q);

      /* Fix quantities on full backorder */
      $q= "SELECT SUM(shipped + backordered) AS ordered
             FROM vendor_order
            WHERE IFNULL(unit,'') != 'AS'";
      $ordered=
        $this->data->for_table('vendor_order')->raw_query($q)->find_one();

      if (!$ordered->ordered) {
        $this->data->execute("UPDATE vendor_order SET backordered = ordered");
      }
    }

    // Identify vendor items by SKU
    $q= "UPDATE vendor_order, vendor_item
            SET vendor_order.item_id = vendor_item.item_id
          WHERE vendor_order.sku != '' AND vendor_order.sku IS NOT NULL
            AND vendor_order.sku = vendor_item.vendor_sku
            AND vendor_id = {$txn->person_id}
            AND vendor_item.active";
    $this->data->execute($q);

    // Identify vendor items by code
    $q= "UPDATE vendor_order, vendor_item
            SET vendor_order.item_id = vendor_item.item_id
          WHERE (NOT vendor_order.item_id OR vendor_order.item_id IS NULL)
            AND vendor_order.item_no != '' AND vendor_order.item_no IS NOT NULL
            AND vendor_order.item_no = vendor_item.code
            AND vendor_id = {$txn->person_id}
            AND vendor_item.active";
    $this->data->execute($q);

    // Identify vendor items by barcode
    $q= "UPDATE vendor_order
            SET item_id = IF(barcode != '',
                          IFNULL((SELECT item.id
                                    FROM item
                                    JOIN barcode ON barcode.item_id = item.id
                                   WHERE vendor_order.barcode = barcode.code
                                   LIMIT 1),
                                 0),
                          0)
          WHERE NOT item_id OR item_id IS NULL";
    $this->data->execute($q);

    // Identify items by code
    $q= "UPDATE vendor_order, item
            SET vendor_order.item_id = item.id
          WHERE (NOT vendor_order.item_id OR vendor_order.item_id IS NULL)
            AND vendor_order.item_no != '' AND vendor_order.item_no IS NOT NULL
            AND vendor_order.item_no = item.code";
    $this->data->execute($q);

    // Identify items by barcode
    $q= "UPDATE vendor_order, barcode
            SET vendor_order.item_id = barcode.item_id
          WHERE (NOT vendor_order.item_id OR vendor_order.item_id IS NULL)
            AND vendor_order.barcode != '' AND vendor_order.barcode IS NOT NULL
            AND vendor_order.barcode = barcode.code";
    $this->data->execute($q);

    // For non-vendor orders, fail if we didn't recognize all items
    if ($txn->type != 'vendor') {
      $count= $this->data->for_table('vendor_order')
                   ->raw_query("SELECT COUNT(*) FROM vendor_order
                                 WHERE (NOT item_id OR item_id IS NULL")
                   ->find_one();
      if ($count) {
        throw new \Exception("Not all items available for order!");
      }
    }

    /* Start a transaction now that we're working with live data */
    $this->data->beginTransaction();

    if ($update_only) {
      $q= "SELECT COUNT(*) total
             FROM vendor_order
            WHERE item_id = 0 or item_id IS NULL";
      $unknown= $this->data->fetch_single_value($q);

      if ($unknown) {
        throw new \Exception("Upload includes items not in the catalog");
      }

      $q= "SELECT COUNT(*) total
             FROM vendor_order
             LEFT JOIN txn_line ON vendor_order.item_id = txn_line.item_id
                               AND txn_id = {$txn->id}
            WHERE txn_line.id IS NULL";
      $unknown= $this->data->fetch_single_value($q);

      if ($unknown) {
        throw new \Exception("Upload includes items not on original order");
      }

      $q= "UPDATE txn_line, vendor_order
              SET allocated = allocated + vendor_order.shipped
            WHERE txn_line.item_id = vendor_order.item_id
              AND txn_id = {$txn->id}";
      $this->data->execute($q);

    } else {
      // Make sure we have all the items
      $q= "INSERT IGNORE INTO item (code, brand_id, name, retail_price, active)
           SELECT item_no AS code,
                  0 AS brand_id,
                  description AS name,
                  msrp AS retail_price,
                  1 AS active
             FROM vendor_order
            WHERE (NOT item_id OR item_id IS NULL) AND msrp > 0 AND IFNULL(unit,'') != 'AS'";
      $this->data->execute($q);

      if ($this->data->get_last_statement()->rowCount()) {
        # Attach order lines to new items
        $q= "UPDATE vendor_order, item
                SET vendor_order.item_id = item.id
              WHERE (NOT vendor_order.item_id OR vendor_order.item_id IS NULL)
                AND vendor_order.item_no != '' AND vendor_order.item_no IS NOT NULL
                AND vendor_order.item_no = item.code";
        $this->data->execute($q);
      }

      // Make sure all the items are active
      $q= "UPDATE item, vendor_order
              SET item.active = 1
            WHERE vendor_order.item_id = item.id";
      $this->data->execute($q);

      // Make sure we know all the barcodes
      $q= "INSERT IGNORE INTO barcode (item_id, code, quantity)
           SELECT item_id,
                  REPLACE(REPLACE(barcode, 'E-', ''), 'U-', '') AS code,
                  1 AS quantity
            FROM vendor_order
           WHERE item_id AND barcode != ''";
      $this->data->execute($q);

      // Link items to vendor items if they aren't already
      $q= "UPDATE vendor_item, vendor_order
              SET vendor_item.item_id = vendor_order.item_id
            WHERE NOT vendor_item.item_id
              AND vendor_item.code = vendor_order.item_no
              AND vendor_item.active";
      $this->data->execute($q);

      // Get pricing for items if vendor_order didn't have them
      $q= "UPDATE vendor_order, vendor_item
              SET msrp = vendor_item.retail_price,
                  net = vendor_item.net_price
            WHERE msrp IS NULL
              AND vendor_order.item_id = vendor_item.item_id
              AND vendor_id = {$txn->person_id}
              AND vendor_item.active";
      $this->data->execute($q);

      // Add items to order
      $q= "INSERT INTO txn_line (txn_id, item_id, ordered, allocated, retail_price)
           SELECT {$txn->id} txn_id, item_id,
                  ordered, shipped, net
             FROM vendor_order
            WHERE (shipped OR backordered)
              AND (item_id != 0 AND item_id IS NOT NULL)";
      $this->data->execute($q);
    }

    $this->data->commit();
  }

  public function checkVendorStock(\Scat\Model\VendorItem $vendor_item) {
    // XXX hardcoded stuff
    switch ($vendor_item->vendor_id) {
    case 7: // Mac
      return $this->check_mac_stock($vendor_item->vendor_sku);
    case 3757: // SLS
      return $this->check_sls_stock($vendor_item->vendor_sku);
    case 30803: // PA Dist
      return $this->check_padist_stock($vendor_item->vendor_sku);
    case 44466: // Notions
      return $this->check_notions_stock($vendor_item->vendor_sku);
    default:
      throw new \Exception("Don't know how to check stock for that vendor.");
      return [];
    }
  }

  function check_mac_stock($code) {
    $url= 'https://www.macphersonart.com/cgi-bin/maclive/wam_tmpl/mac_cart.p';

    $key= $this->config->get('vendor.macp.key');

    $client= new \GuzzleHttp\Client();
    $jar= \GuzzleHttp\Cookie\CookieJar::fromArray([ 'liveWAMSession' => $key ],
                                                  parse_url($url, \PHP_URL_HOST));

    $res= $client->request('GET', $url,
                           [
                           //'debug' => true,
                             'cookies' => $jar,
                             'query' => [
                               'site' => 'MAC',
                               'layout' => 'Responsive',
                               'nocache' => 66439,
                               'content' => 'JSON',
                               'page' => 'mac_cart',
                               'action' => 'getItemInfo',
                               'itemNumber' => $code,
                               'dropship' => 'false'
                             ]
                           ]);

    $body= $res->getBody();
    if ($GLOBALS['DEBUG']) {
      error_log($body);
    }

    $data= json_decode($body);
    $avail= [];

    foreach ($data->response->itemInfo as $item) {
      foreach ($item->itemQty as $qty) {
        $avail[$qty->warehouseName]= $qty->qty;
      }
    }

    return $avail;
  }

  function check_sls_stock($code) {
    $client= new \GuzzleHttp\Client();
    $jar= new \GuzzleHttp\Cookie\CookieJar();

    $url= 'https://www.slsarts.com/loginpage.asp';

    $user= $this->config->get('vendor.sls.username');
    $pass= $this->config->get('vendor.sls.password');

    $res= $client->request('POST', $url,
                           [
                           //'debug' => true,
                             'cookies' => $jar,
                             'form_params' => [
                               'level1' => '',
                               'level2' => '',
                               'level3' => '',
                               'level4' => '',
                               'level5' => '',
                               'skuonly' => '',
                               'txtfind' => '',
                               'snum' => '',
                               'skey' => '',
                               'username' => $user,
                               'password' => $pass,
                               'btnlogin' => 'Login'
                             ]
                           ]);

    $url= 'https://www.slsarts.com/viewcarttop.asp';
    $res= $client->request('POST', $url,
                           [
                           //'debug' => true,
                             'cookies' => $jar,
                             'form_params' => [
                               'defwh' => 2,
                               'imaacr' => '',
                               'forcebottom' => 'N',
                               'slssku' => $code,
                               'slsskuorig' => '',
                               'slsqty' => '',
                               'WH' => 2,
                               'oldwh' => '',
                               'goclear' => '',
                               'vmwvnm' => '',
                               'imadsc' => '',
                               'msrp' => '',
                               'minqty' => '',
                               'qoh1' => '',
                               'price' => '',
                               'disc' => 0,
                               'qoh2' => '',
                               'borderitem' => 'Order Item',
                               'verror' => '',
                               'vonblur' => 'Y',
                               'vedititem' => '',
                               'veditwh' => '',
                               'vdelitem' => '',
                               'vdropship' => 'E',
                               'deletelist' => '',
                               'thepos' => '',
                             ]
                           ]);

    $body= $res->getBody();

    $dom= new \DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($body);

    $xp= new \DOMXpath($dom);
    $no= $xp->query('//input[@name="qoh1"]')->item(0)->getAttribute('value');
    $vg= $xp->query('//input[@name="qoh2"]')->item(0)->getAttribute('value');

    return [ 'Vegas' => $vg, 'New Orleans' => $no ];
  }

  function check_padist_stock($code) {
    $lookup_url= 'https://www.pa-dist.com/ajax/search/suggestions';

    $key= $this->config->get('vendor.padist.key');

    $client= new \GuzzleHttp\Client();
    $jar= \GuzzleHttp\Cookie\CookieJar::fromArray(
      [ 'WebLogin' => $key ],
      parse_url($lookup_url, PHP_URL_HOST)
    );

    $res= $client->request('GET', $lookup_url,
                           [
                             'cookies' => $jar,
                             'query' => [
                               'q' => $code
                             ]
                           ]);

    $body= $res->getBody();
    if ($GLOBALS['DEBUG']) {
      error_log($body);
    }

    // gross, but we're parsing html with a regex. ¯\_(ツ)_/¯
    if (preg_match("!/Item/(\d+)[^']+'><item-number><i>" . preg_quote($code) . '</i></item-number>!', $body, $m)) {
      $id= $m[1];
    } else {
      throw new \Exception("Unable to find ID for $code");
    }

    $details_url= 'https://www.pa-dist.com/ajax/item/detail/' . $id;

    $res= $client->request('GET', $details_url,
                           [
                             'cookies' => $jar,
                           ]);

    $body= $res->getBody();
    if ($GLOBALS['DEBUG']) {
      error_log($body);
    }

    $dom= new \DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($body);

    $xp= new \DOMXpath($dom);
    $stock= $xp->query('//span[@class="StockLevel"]')->item(0)->textContent;

    return [ 'stock' => $stock ];
  }

  function check_notions_stock($code) {
    $lookup_url= 'https://my.notionsmarketing.com/integration-web-services/services_rest/erpservice/qasInquiry';

    $client= new \GuzzleHttp\Client();

    $res= $client->request('POST', $lookup_url, [
      'headers' => [
        'Content-type' => 'application/json',
        'Accept' => 'application/json'
      ],
      'json' => [
        'skulist' => sprintf('%06d', $code)
      ]
    ]);

    $body= $res->getBody();

    $data= json_decode($body);

    return [ 'stock' => $data->qas ];
  }
}
