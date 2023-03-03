<?php
namespace Scat\Model;

use \Respect\Validation\Validator as v;

class Person extends \Scat\Model {

  public function friendly_name() {
    if ($this->name || $this->company) {
      return $this->name .
             ($this->name && $this->company ? ' / ' : '') .
             $this->company;
    }
    if ($this->email) {
      return $this->email;
    }
    if ($this->phone) {
      return $this->pretty_phone();
    }
    return $this->id;
  }

  function pretty_phone() {
    if ($this->phone) {
      try {
        $phoneUtil= \libphonenumber\PhoneNumberUtil::getInstance();
        $num= $phoneUtil->parse($this->phone, 'US');
        return $phoneUtil->format($num,
                                  \libphonenumber\PhoneNumberFormat::NATIONAL);
      } catch (\Exception $e) {
        // Punt!
        return $this->phone;
      }
    }
  }

  public function loyalty() {
    return $this->has_many('Loyalty');
  }

  public function points_available() {
    if ($this->suppress_loyalty) return 0;
    return $this->loyalty()
                ->where_raw("(points < 0 OR DATE(processed) < DATE(NOW()))")
                ->sum('points');
  }

  public function points_pending() {
    if ($this->suppress_loyalty) return 0;
    return $this->loyalty()
                ->where_raw("(points > 0 AND DATE(processed) >= DATE(NOW()))")
                ->sum('points');
  }

  function available_loyalty_rewards() {
    $points= $this->points_available();

    return $this->factory('LoyaltyReward')->where_lte('cost', $points);
  }

  public function jsonSerialize() {
    $data= parent::jsonSerialize();
    /* Need to decode our JSON field */
    $data['subscriptions']= json_decode($this->subscriptions);
    $data['friendly_name']= $this->friendly_name();
    $data['pretty_phone']= $this->pretty_phone();
    $data['points_available']= $this->points_available();
    $data['points_pending']= $this->points_pending();
    return $data;
  }

  static function find($q, $all= false) {
    $criteria= [];

    $terms= preg_split('/\s+/', trim($q));
    foreach ($terms as $term) {
      if (preg_match('/id:(\d*)/', $term, $m)) {
        $id= (int)$m[1];
        $criteria[]= "(person.id = $id)";
        $all= true;
      } else if (preg_match('/role:(.+)/', $term, $m)) {
        $role= addslashes($m[1]);
        $criteria[]= "(person.role = '$role')";
      } elseif (preg_match('/^active:(.+)/i', $term, $dbt)) {
        $criteria[]= $dbt[1] ? "(person.active)" : "(NOT person.active)";
        $all= true;
      } else {
        $term= addslashes($term);
        $criteria[]= "(person.name LIKE '%$term%'
                   OR person.company LIKE '%$term%'
                   OR person.email LIKE '%$term%'
                   OR person.loyalty_number LIKE '%$term%'
                   OR person.phone LIKE '%$term%'
                   OR person.notes LIKE '%$term%')";
      }
    }

    $sql_criteria= join(' AND ', $criteria);

    $people= self::factory('Person')->select('person.*')
                                      ->where_raw($sql_criteria)
                                      ->where_gte('person.active',
                                                  $all ? 0 : 1)
                                      ->where_not_equal('person.deleted', 1)
                                      ->order_by_asc('company')
                                      ->order_by_asc('name')
                                      ->order_by_asc('loyalty_number')
                                      ->find_many();

    return $people;
  }

  public function items($only_active= true) {
    if ($this->role != 'vendor') {
      throw new \Exception("People who are not vendors don't have items.");
    }
    return $this->has_many('VendorItem', 'vendor_id')
                ->where_gte('active', (int)$only_active);
  }

  public function open_orders() {
    return $this->has_many('Txn')
                ->where_in('status', [ 'new' ])
                ->find_many();
  }

  public function txns($page= 0, $limit= 25) {
    return $this->has_many('Txn')
                ->select('*')
                ->select_expr('COUNT(*) OVER()', 'records')
                ->order_by_desc('created')
                ->limit($limit)->offset($page * $limit);
  }

  public function setProperty($name, $value) {
    $value= trim($value);
    if ($name == 'phone') {
      v::optional(v::phone())->assert($value);
      $this->phone= $value ?: null;
      $this->loyalty_number= preg_replace('/[^\d]/', '', $value) ?: null;
    }
    else if ($name == 'email') {
      v::optional(v::email())->assert($value);
      $this->email= $value ?: null;
    }
    else if ($name == 'mailerlite_id') {
      $this->mailerlite_id= $value ?: null;
      if ($value) {
        $this->syncToMailerlite();
      }
    }
    else if ($name == 'rewardsplus') {
      /*
       * If this is a new opt-in to Rewards+, need to send welcome
       * and compliance message.
       */
      if ($value && !$this->rewardsplus && $this->loyalty_number) {
        $config= $GLOBALS['container']->get(\Scat\Service\Config::class);
        $phone= $GLOBALS['container']->get(\Scat\Service\Phone::class);
        $message= $config->get('rewards.signup_message');
        $compliance= 'Reply STOP to unsubscribe or HELP for help. 6 msgs per month, Msg&Data rates may apply.';
        //$phone->sendSMS($this->loyalty_number, $message);
        //$phone->sendSMS($this->loyalty_number, $compliance);
      }
      $this->$name= $value;
    }
    /* If we already have a giftcard attached, attaching a new one will
     * transfer the balance. */
    else if ($name == 'giftcard_id' && $this->giftcard_id) {
      $giftcard= $this->factory('Giftcard')->find_one($value);
      if (!$giftcard) {
        throw new \Exception("Unable to find giftcard '$giftcard_id'.");
      }

      $store_credit= $this->factory('Giftcard')->find_one($this->giftcard_id);
      if (!$store_credit) {
        throw new \Exception("Unable to find store credit '{$this->giftcard_id}'.");
      }

      $amount= $giftcard->balance();

      $giftcard->add_txn(-$amount);
      $store_credit->add_txn($amount);
    }
    elseif (isset($this, $name)) {
      $this->$name= ($value !== '') ? $value : null;
    } else {
      throw new \Exception("No way to set '$name' on a person.");
    }
  }

  public function loadVendorData($file) {
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
      if (!$this->orm->raw_execute($q))
        throw new \Exception("Unable to drop 'vendor_upload'");
      $temporary= "";
    }

    $q= "CREATE $temporary TABLE vendor_upload LIKE vendor_item";
    if (!$this->orm->raw_execute($q))
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

      if (!$this->orm->raw_execute($q))
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

      if (!$this->orm->raw_execute($q))
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

      if (!$this->orm->raw_execute($q))
        throw new \Exception("Unable to load SLS data file");

      // delete assortments
      $q= "DELETE FROM vendor_upload WHERE special_order = 2";
      if (!$this->orm->raw_execute($q))
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

      if (!$this->orm->raw_execute($q))
        throw new \Exception("Unable to load ColArt data file");

      // toss junk from header lines
      $q= "DELETE FROM vendor_upload WHERE purchase_quantity = 0";

      if (!$this->orm->raw_execute($q))
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

      if (!$this->orm->raw_execute($q))
        throw new \Exception("Unable to load ColArt data file");

      // toss junk from header lines
      $q= "DELETE FROM vendor_upload WHERE promo_quantity = 0";

      if (!$this->orm->raw_execute($q))
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

      if (!$this->orm->raw_execute($q))
        throw new \Exception("Unable to load Masterpiece data file");
      // toss junk from header lines
      $q= "DELETE FROM vendor_upload WHERE purchase_quantity = 0";

      if (!$this->orm->raw_execute($q))
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

      if (!$this->orm->raw_execute($q))
        throw new \Exception("Unable to load PA Dist data file");

      // toss junk from header lines
      $q= "DELETE FROM vendor_upload WHERE purchase_quantity = 0";

      if (!$this->orm->raw_execute($q))
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

      if (!$this->orm->raw_execute($q))
        throw new \Exception("Unable to load PA Dist data file");

      // toss junk from header lines
      $q= "DELETE FROM vendor_upload WHERE purchase_quantity = 0";

      if (!$this->orm->raw_execute($q))
        throw new \Exception("Unable to load PA Dist data file");
    } elseif (preg_match('/^golden/', $line)) {
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

      if (!$this->orm->raw_execute($q))
        throw new \Exception("Unable to load PA Dist data file");

      // toss junk from header lines
      $q= "DELETE FROM vendor_upload WHERE purchase_quantity = 0";

      if (!$this->orm->raw_execute($q))
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
               @retail_price, @net_price, @promo_price,
               @barcode, @purchase_quantity, @promo_quantity,
               weight, length, width, height)
           SET
               code = TRIM(@code),
               vendor_sku = TRIM(@vendor_sku),
               name = TRIM(@name),
               retail_price = REPLACE(REPLACE(@retail_price, ',', ''), '$', ''),
               net_price = REPLACE(REPLACE(@net_price, ',', ''), '$', ''),
               promo_price = REPLACE(REPLACE(@promo_price, ',', ''), '$', ''),
               barcode = REPLACE(REPLACE(@barcode, '-', ''), ' ', ''),
               purchase_quantity = IF(@purchase_quantity, @purchase_quantity, 1),
               promo_quantity = IF(@promo_quantity, @promo_quantity,
                                   IF(@promo_price, purchase_quantity, NULL))";

      if (!$this->orm->raw_execute($q))
        throw new \Exception("Unable to load generic data file");
    }
    /* END Vendors */

    /* Just toss bad barcodes to avoid grief */
    $q= "UPDATE vendor_upload SET barcode = NULL WHERE LENGTH(barcode) < 3";
    if (!$this->orm->raw_execute($q))
      throw new \Exception("Unable to toss bad barcodes");

    /* If we are replacing vendor data, mark the old stuff inactive */
    if ($action == 'replace') {
      $q= "UPDATE vendor_item SET active = 0 WHERE vendor_id = {$this->id}";
      if (!$this->orm->raw_execute($q))
        throw new \Exception("Unable to deactive old items");
    }
    /* If this is a promo, unset existing promos for this vendor */
    if ($action == 'promo') {
      $q= "UPDATE vendor_item SET promo_price = NULL, promo_quantity = NULL
            WHERE vendor_id = {$this->id}";
      if (!$this->orm->raw_execute($q))
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
                {$this->id} AS vendor,
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

    if (!$this->orm->raw_execute($q))
      throw new \Exception("Unable to add and update items");

    $added_or_updated= \Titi\ORM::get_last_statement()->rowCount();

    // Find by code/item_no
    $q= "UPDATE vendor_item
            SET item_id = IFNULL((SELECT id FROM item
                                   WHERE vendor_item.code = item.code
                                     AND item.active),
                              0)
         WHERE vendor_id = {$this->id}
           AND (item_id IS NULL OR item_id = 0)";
    if (!$this->orm->raw_execute($q))
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
         WHERE vendor_id = {$this->id}
           AND (item_id IS NULL OR item_id = 0)
           AND barcode IS NOT NULL
           AND barcode != ''";
    if (!$this->orm->raw_execute($q))
      throw new \Exception("Unable to match items by barcode");

    return [ "message" => "Added or updated " . $added_or_updated . " items." ];
  }

  public function punches() {
    return $this->has_many('Timeclock');
  }

  public function punched() {
    $punch= $this->punches()->where_null('end')->find_one();
    return $punch ? $punch->start : null;
  }

  public function last_punch_out() {
    $punch= $this->punches()->where_not_null('end')->order_by_desc('id')->find_one();
    return $punch ? $punch->end : null;
  }

  public function punch() {
    $punch= $this->punches()->where_null('end')->find_one();
    if ($punch) {
      $punch->set_expr('end', 'NOW()');
      $punch->save();
    } else {
      $punch= $this->punches()->create();
      $punch->person_id= $this->id();
      $punch->set_expr('start', 'NOW()');
      $punch->save();
    }
    return $punch;
  }

  public function subscriptions($update= null) {
    if ($update) {
      $this->subscriptions= json_encode($update);
    }

    return json_decode($this->subscriptions);
  }

  public function syncToMailerlite() {
    // XXX There must be a better way to get this.
    $config= $GLOBALS['container']->get(\Scat\Service\Config::class);

    try {
      $client= new \GuzzleHttp\Client();

      $url= "https://api.mailerlite.com/api/v2" .
            "/subscribers/{$this->email}/groups";

      $res= $client->request('GET', $url, [
                              //'debug' => true,
                              'headers' => [
                                'X-MailerLite-ApiKey' =>
                                  $config->get("mailerlite.key")
                              ],
                            ]);

      $data= json_decode($res->getBody());

      $groups= array_map(function($group) {
        return [ 'id' => $group->id, 'name' => $group->name ];
      }, $data);

      $this->subscriptions($groups);
    } catch (\Exception $e) {
      // log and go on with our life
      error_log("Exception: " . $e->getMessage());
    }
  }

  public function store_credit() {
    return $this->giftcard_id ? $this->belongs_to('Giftcard')->find_one() : null;
  }

  public function xnotes() {
    return
      $this->has_many('Note', 'attach_id')
        ->where('parent_id', 0)
        ->where('kind', 'person');
  }

}
