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
      } catch (Exception $e) {
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
                ->where_raw("(points > 0 AND DATE(processed) > DATE(NOW()))")
                ->sum('points');
  }

  public function jsonSerialize() {
    $data= parent::jsonSerialize();
    /* Need to decode our JSON field */
    $data['subscriptions']= json_decode($this->subscriptions);
    $data['friendly_name']= $this->friendly_name();
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

    $people= \Model::factory('Person')->select('person.*')
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
                ->where_null('filled')
                ->find_many();
  }

  public function txns($page= 0, $limit= 25) {
    return $this->has_many('Txn')
                ->select('*')
                ->select_expr('COUNT(*) OVER()', 'records')
                ->order_by_desc('created')
                ->limit($limit)->offset($page * $limit)
                ->find_many();
  }

  public function setProperty($name, $value) {
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
        $phone->sendSMS($this->loyalty_number, $message);
        $phone->sendSMS($this->loyalty_number, $compliance);
      }
      $this->$name= $value;
    }
    elseif (isset($this, $name)) {
      $this->$name= $value ?: null;
    } else {
      throw new \Exception("No way to set '$name' on a person.");
    }
  }

  public function loadVendorData($file) {
    $tmpfn= $file->file;
    $fn= $file->getClientFilename();

    /* Grab the first line for detecting file type */
    $stream= $file->getStream();
    $line= fgets($stream);
    fclose($file);

    ob_start();

    $action= 'replace';

    // TODO should throw better exceptions throughout

    /* Load uploaded file into a temporary table */
# On DEBUG server, we leave behind the vendor_upload table for debugging
    $temporary= "TEMPORARY";
    // XXX using global $DEBUG
    if ($GLOBALS['DEBUG']) {
      $q= "DROP TABLE IF EXISTS vendor_upload";
      if (!\ORM::raw_execute($q))
        throw new \Exception("Unable to drop 'vendor_upload'");
      $temporary= "";
    }

    $q= "CREATE $temporary TABLE vendor_upload LIKE vendor_item";
    if (!\ORM::raw_execute($q))
      throw new \Exception("Unable to create 'vendor_upload'");

    /* START Vendors */
    if (preg_match('/MACITEM.*\.zip$/i', $fn)) {
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
            SET vendor_sku = code, special_order = IF(@abc_flag = 'S', 1, 0),
                promo_quantity = purchase_quantity";

      if (!\ORM::raw_execute($q))
        throw new \Exception("Unable to load Mac data file");

    } elseif (preg_match('/^"?sls_sku"?(,|\t)/', $line, $m)) {
      // SLS
#sls_sku,cust_sku,description,vendor_name,msrp,reg_price,reg_discount,promo_price,promo_discount,upc1,upc2,upc2_qty,upc3,upc3_qty,min_ord_qty,level1,level2,level3,level4,level5,ltl_only,add_date,ormd,prop65,no_calif,no_canada,

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
                prop65 = IF(@prop65 = 'Y', 1, NULL),
                hazmat = IF(@ormd = 'Y', 1, NULL),
                oversized = IF(@ltl_only = 'Y', 1, NULL),
                promo_quantity = purchase_quantity";

      if (!\ORM::raw_execute($q))
        throw new \Exception("Unable to load SLS data file");

    } elseif (preg_match('/Alvin SRP/', $line)) {
      // Alvin Account Pricing Report
#Manufacturer	BrandName	SubBrand	AlvinItem#	Description	New	UoM	Alvin SRP	RegularMultiplier	RegularNet	CurrentMultiplier	CurrentNetPrice	CurrentPriceSource	SaleStarted	SaleExpiration	Buying Quantity (BQ)	DropShip	UPC or EAN	Weight	Length	Width	Height	Ship Truck	CountryofOrigin	HarmonizedCode	DropShipDiscount	CatalogPage	VendorItemNumber
      $sep= preg_match("/\t/", $line) ? "\t" : ",";
      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
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

      if (!\ORM::raw_execute($q))
        throw new \Exception("Unable to load Alvin data file");

    } elseif (preg_match('/Golden Ratio/', $line)) {
      // Masterpiece
      $sep= preg_match("/\t/", $line) ? "\t" : ",";

#,SN,PK Sort,SKU Sort,,SKU,Golden Ratio,Size,Item Description,,,,,,,,,2020 Retail,Under $500 Net Order,Net $500 Order,Units Per Pkg,Pkgs Per Box,Weight,UPC,Freight Status,DIM. Weight,Est. Freight EACH,Est. Freight CASE,Item length,Item Width,Item Height,Carton Length,Carton Width,Carton Height,HTS,Origin,

      $q= "LOAD DATA LOCAL INFILE '$tmpfn'
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
               @est_freight, @est_freight_case,
               length, width, height,
               @carton_length, @carton_width, @carton_height, @hts, @origin)
            SET code = CONCAT('MA', vendor_sku),
                retail_price = REPLACE(REPLACE(@retail_price, ',', ''), '$', ''),
                net_price = REPLACE(REPLACE(@net_price, ',', ''), '$', ''),
                promo_price = REPLACE(REPLACE(@promo_price, ',', ''), '$', ''),
                promo_quantity = purchase_quantity,
                name = IF(@size, CONCAT(@size, ' ', @description), @description)";

      if (!\ORM::raw_execute($q))
        throw new \Exception("Unable to load Masterpiece data file");

      // toss junk from header lines
      $q= "DELETE FROM vendor_upload WHERE purchase_quantity = 0";

      if (!\ORM::raw_execute($q))
        throw new \Exception("Unable to load Masterpiece data file");

    } else {
      // Generic
      if (preg_match('/\t/', $line)) {
        $format= "FIELDS TERMINATED BY '\t' OPTIONALLY ENCLOSED BY '\"'";
      } else {
        $format= "FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"'";
      }
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

      if (!\ORM::raw_execute($q))
        throw new \Exception("Unable to load generic data file");
    }
    /* END Vendors */

    /* Just toss bad barcodes to avoid grief */
    $q= "UPDATE vendor_upload SET barcode = NULL WHERE LENGTH(barcode) < 3";
    if (!\ORM::raw_execute($q))
      throw new \Exception("Unable to toss bad barcodes");

    /* If we are replacing vendor data, mark the old stuff inactive */
    if ($action == 'replace') {
      $q= "UPDATE vendor_item SET active = 0 WHERE vendor_id = {$this->id}";
      if (!\ORM::raw_execute($q))
        throw new \Exception("Unable to deactive old items");
    }
    /* If this is a promo, unset existing promos for this vendor */
    if ($action == 'promo') {
      $q= "UPDATE vendor_item SET promo_price = NULL, promo_quantity = NULL
            WHERE vendor_id = {$this->id}";
      if (!\ORM::raw_execute($q))
        throw new \Exception("Unable to clear old promos");
    }

    $q= "INSERT INTO vendor_item
                (vendor, item, code, vendor_sku, name,
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

    if (!\ORM::raw_execute($q))
      throw new \Exception("Unable to add and update items");

    $added_or_updated= $db->affected_rows;

    // Find by code/item_no
    $q= "UPDATE vendor_item
            SET item_id = IFNULL((SELECT id FROM item
                                WHERE vendor_item.code = item.code),
                              0)
         WHERE vendor_id = {$this->id}
           AND (item_id IS NULL OR item_id = 0)";
    if (!\ORM::raw_execute($q))
      throw new \Exception("Unable to match items by code");

    // Find by barcode
    $q= "UPDATE vendor_item
            SET item_id = IFNULL((SELECT item_id FROM barcode
                                WHERE barcode.code = barcode
                                LIMIT 1),
                              0)
         WHERE vendor_id = {$this->id}
           AND (item_id IS NULL OR item_id = 0)
           AND barcode IS NOT NULL
           AND barcode != ''";
    if (!\ORM::raw_execute($q))
      throw new \Exception("Unable to match items by barcode");

    return [ "result" => "Added or updated " . $added_or_updated . " items." ];
  }

  public function punches() {
    return $this->has_many('Timeclock');
  }

  public function punched() {
    $punch= $this->punches()->where_null('end')->find_one();
    return $punch->start;
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

    $client= new \GuzzleHttp\Client();

    $url= "https://api.mailerlite.com/api/v2" .
          "/subscribers/{$this->mailerlite_id}/groups";

    $res= $client->request('GET', $url, [
                            //'debug' => true,
                            'headers' => [
                              'X-MailerLite-ApiKey' =>
                                $config->get("newsletter.key")
                            ],
                          ]);

    $data= json_decode($res->getBody());

    $groups= array_map(function($group) {
      return [ 'id' => $group->id, 'name' => $group->name ];
    }, $data);

    $this->subscriptions($groups);

  }
}
