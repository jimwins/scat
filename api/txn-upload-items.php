<?
require '../scat.php';
require '../lib/txn.php';

$txn_id= (int)$_REQUEST['txn'];

if (!$txn_id)
  die_jsonp("No transaction specified.");

$txn= txn_load($db, $txn_id);

$fn= $_FILES['src']['tmp_name'];

if (!$fn)
  die_jsonp("No file uploaded");

ob_start();

$file= fopen($fn, 'r');
$line= fgets($file);
fclose($file);

$temporary= "TEMPORARY";

# On DEBUG server, we leave behind the vendor_order table
if ($DEBUG) {
  $q= "DROP TABLE IF EXISTS vendor_order";
  $db->query($q)
    or die_query($db, $q);
  $temporary= "";
}

$q= "CREATE $temporary TABLE vendor_order (
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
       box_no varchar(255),
       key (item), key(item_no), key(sku))";

$db->query($q)
  or die_query($db, $q);

// SLS order?
if (preg_match('/^linenum[,\t]qty/', $line)) {
  // linenum,qty_shipped,sls_sku,cust_item_numb,description,upc,msrp,net_cost,pkg_id,extended_cost
  $sep= preg_match("/,/", $line) ? "," : "\t";
  $q= "LOAD DATA LOCAL INFILE '$fn'
       INTO TABLE vendor_order
       FIELDS TERMINATED BY '$sep' OPTIONALLY ENCLOSED BY '\"'
       IGNORE 1 LINES
       (line, @shipped, item_no, cust_item, description, @upc,
        msrp, net, box_no, ext)
       SET barcode = REPLACE(@upc, 'UPC->', ''),
           sku = item_no,
           ordered = @shipped, backordered = @shipped, shipped = 0";
  $db->query($q)
    or die_query($db, $q);

  echo "- Loaded ", $db->affected_rows, " rows from file.\n";

// C2F order (CSV)
} elseif (preg_match('/^"?LineNumber/', $line)) {
  //LineNumber,ProductID,ProductDesc,UOM,UPC_EAN,QtyOrdered,QtyShipped,QtyBackOrdered,RetailPrice,UnitAmt
  $sep= preg_match("/,/", $line) ? "," : "\t";
  $q= "LOAD DATA LOCAL INFILE '$fn'
       INTO TABLE vendor_order
       FIELDS TERMINATED BY '$sep' OPTIONALLY ENCLOSED BY '\"'
       IGNORE 1 LINES
       (line, item_no, description, status, @upc,
        @ordered, @shipped, @backordered, msrp, net)
       SET barcode = REPLACE(@upc, 'UPC->', ''),
           sku = item_no, cust_item = @uom,
           ordered = @shipped, backordered = @shipped, shipped = 0";
  $db->query($q)
    or die_query($db, $q);

  echo "- Loaded ", $db->affected_rows, " rows from file.\n";

  $db->query("DELETE FROM vendor_order WHERE cust_item IN ('AS','ASMT')");

  echo "- Removed ", $db->affected_rows, " assortments from file.\n";

// C2F order (XLS)
} elseif (preg_match('/xls/', $_FILES['src']['name'])) {
  $reader= new \PhpOffice\PhpSpreadsheet\Reader\Xls();
  $reader->setReadDataOnly(true);

  $spreadsheet= $reader->load($fn);
  $sheet= $spreadsheet->getActiveSheet();
  $i= 0; $rows= [];
  foreach ($sheet->getRowIterator() as $row) {
    if ($i++) {
      $data= [];
      $cellIterator= $row->getCellIterator();
      $cellIterator->setIterateOnlyExistingCells(false);
      foreach ($cellIterator as $cell) {
        $data[]= "'" . $db->escape($cell->getValue()) . "'";
      }
      $rows[]= '(' . join(',', $data) . ')';
    }
  }

  $q= "INSERT INTO vendor_order (line, item_no, description, unit, barcode, ordered, shipped, backordered, msrp, net) VALUES " . join(',', $rows);
  $db->query($q)
    or die_query($db, $q);

  echo "- Loaded ", $db->affected_rows, " rows from file.\n";

  // Reset numbers to just what was shipped
  $q= "UPDATE vendor_order
          SET ordered = shipped, backordered = shipped, shipped = 0";
  $db->query($q)
    or die_query($db, $q);

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

  echo "- Loaded ", $db->affected_rows, " rows from file.\n";

} elseif (preg_match('/^"?sls_sku.*asst_qty/', $line)) {
  // SLS assortment
  # sls_sku,cust_sku,description,vendor_name,msrp,reg_price,reg_discount,promo_price,promo_discount,upc1,upc2,upc2_qty,upc3,upc3_qty,min_ord_qty,level1,level2,level3,level4,level5,ltl_only,add_date,asst_qty,
  $sep= preg_match("/,/", $line) ? "," : "\t";
  $q= "LOAD DATA LOCAL INFILE '$fn'
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
  $db->query($q)
    or die_query($db, $q);

  echo "- Loaded ", $db->affected_rows, " rows from SLS assortment.\n";
} elseif (preg_match('/^C2F Product #/', $line)) {
  // C2F
  $q= "LOAD DATA LOCAL INFILE '$fn'
       INTO TABLE vendor_order
       FIELDS TERMINATED BY '\t'
       LINES TERMINATED BY '\r\n'
       IGNORE 1 LINES
       (item_no, @qty, status, description, msrp, net, barcode)
       SET ordered = @qty, shipped = @qty";
  $db->query($q)
    or die_query($db, $q);

  echo "- Loaded ", $db->affected_rows, " rows from file.\n";

  // Don't import assortments (have to do that manually)
  $db->query("DELETE FROM vendor_order WHERE status = 'asmt' OR status = 'as'");

  echo "- Deleted ", $db->affected_rows, " assortments from file.\n";
} elseif (preg_match('/^Item #	Description	Qty	UPC/', $line)) {
  // C2F Assortment
  echo "- Loading C2F Assortment\n";
  $q= "LOAD DATA LOCAL INFILE '$fn'
       INTO TABLE vendor_order
       FIELDS TERMINATED BY '\t' OPTIONALLY ENCLOSED BY '\"'
       IGNORE 1 LINES
       (item_no, description, @qty, barcode)
       SET ordered = @qty, shipped = @qty, msrp = 1, net = 1";
  $db->query($q)
    or die_query($db, $q);

  echo "- Loaded ", $db->affected_rows, " rows from file.\n";
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

  echo "- Loaded ", $db->affected_rows, " rows from file.\n";
} elseif (preg_match('/^code\tqty/', $line)) {
  // Order file
  $q= "LOAD DATA LOCAL INFILE '$fn'
       INTO TABLE vendor_order
       FIELDS TERMINATED BY '\t' OPTIONALLY ENCLOSED BY '\"'
       IGNORE 1 LINES
       (item_no, @qty)
       SET ordered = @qty, shipped = @qty";
  $db->query($q)
    or die_query($db, $q);

  echo "- Loaded ", $db->affected_rows, " rows from file.\n";

  // Need to get price, item info from vendor info
  $q= "UPDATE vendor_order, vendor_item
          SET vendor_order.item = vendor_item.item,
              msrp = vendor_item.retail_price,
              net = vendor_item.net_price
        WHERE vendor_order.item_no = vendor_item.code
          AND vendor = $txn[person]
          AND vendor_item.active";
  $db->query($q)
    or die_query($db, $q);

  echo "- Updated ", $db->affected_rows, " rows from vendor info.\n";

} elseif (($json= json_decode(file_get_contents($fn)))) {
  // JSON
  foreach ($json->items as $item) {
    $q= "INSERT INTO vendor_order
            SET item_no = '" . $db->escape($item->code) . "',
                description = '" . $db->escape($item->name) . "',
                ordered = -" . (int)$item->quantity . ",
                shipped = -" . (int)$item->quantity . ",
                msrp = '" . $db->escape($item->retail_price) . "',
                net = '" . $db->escape($item->sale_price) . "'";
    // XXX handle vendor vs. non-vendor for quantity
    $db->query($q)
      or die_query($db, $q);
  }

} else {
  // MacPherson's order
  $q= "LOAD DATA LOCAL INFILE '$fn'
       INTO TABLE vendor_order
       CHARACTER SET 'latin1'
       FIELDS TERMINATED BY '\t' OPTIONALLY ENCLOSED BY '\"'
       IGNORE 1 LINES
       (line, status, item_no, sku, cust_item, description, ordered,
        shipped, backordered, msrp, discount, net, unit, ext, barcode,
        account_no, po_no, order_no, bo_no, invoice_no, box_no)";
  $db->query($q)
    or die_query($db, $q);

  echo "- Loaded ", $db->affected_rows, " rows from file.\n";

  /* Fix quantities on backorders */
  $q= "SELECT SUM(shipped + backordered)
         FROM vendor_order
        WHERE IFNULL(unit,'') != 'AS'";
  $ordered= $db->get_one($q);

  if (!$ordered) {
    $db->query("UPDATE vendor_order SET backordered = ordered")
      or die_query($db, $q);
  }
}

$q= "START TRANSACTION";
$db->query($q)
  or die_query($db, $q);

# Identify vendor items by SKU
$q= "UPDATE vendor_order, vendor_item
        SET vendor_order.item = vendor_item.item
      WHERE vendor_order.sku != '' AND vendor_order.sku IS NOT NULL
        AND vendor_order.sku = vendor_item.vendor_sku
        AND vendor = $txn[person]
        AND vendor_item.active";
$db->query($q)
  or die_query($db, $q);

# Identify vendor items by code
$q= "UPDATE vendor_order, vendor_item
        SET vendor_order.item = vendor_item.item
      WHERE (NOT vendor_order.item OR vendor_order.item IS NULL)
        AND vendor_order.item_no != '' AND vendor_order.item_no IS NOT NULL
        AND vendor_order.item_no = vendor_item.code
        AND vendor = $txn[person]
        AND vendor_item.active";
$db->query($q)
  or die_query($db, $q);

# Identify vendor items by barcode
$q= "UPDATE vendor_order
        SET item = IF(barcode != '',
                      IFNULL((SELECT item.id
                                FROM item
                                JOIN barcode ON barcode.item = item.id
                               WHERE vendor_order.barcode = barcode.code
                               LIMIT 1),
                             0),
                      0)
      WHERE NOT item OR item IS NULL";
$db->query($q)
  or die_query($db, $q);

# Identify items by code
$q= "UPDATE vendor_order, item
        SET vendor_order.item = item.id
      WHERE (NOT vendor_order.item OR vendor_order.item IS NULL)
        AND vendor_order.item_no != '' AND vendor_order.item_no IS NOT NULL
        AND vendor_order.item_no = item.code";
$db->query($q)
  or die_query($db, $q);

# Identify items by barcode
$q= "UPDATE vendor_order, barcode
        SET vendor_order.item = barcode.item
      WHERE (NOT vendor_order.item OR vendor_order.item IS NULL)
        AND vendor_order.barcode != '' AND vendor_order.barcode IS NOT NULL
        AND vendor_order.barcode = barcode.code";
$db->query($q)
  or die_query($db, $q);

# For non-vendor orders, fail if we didn't recognize all items
if ($txn['type'] != 'vendor') {
  if ($db->get_one("SELECT COUNT(*) FROM vendor_order
                     WHERE (NOT item OR item IS NULL")) {
    die_jsonp("Not all items available for order!");
  }
}

# Make sure we have all the items
$q= "INSERT IGNORE INTO item (code, brand, name, retail_price, active)
     SELECT item_no AS code,
            0 AS brand,
            description AS name,
            msrp AS retail_price,
            1 AS active
       FROM vendor_order
      WHERE (NOT item OR item IS NULL) AND msrp > 0 AND IFNULL(unit,'') != 'AS'";
$db->query($q)
  or die_query($db, $q);
echo "- Loaded ", $db->affected_rows, " items from order.\n";

if ($db->affected_rows) {
  # Attach order lines to new items
  $q= "UPDATE vendor_order, item
          SET vendor_order.item = item.id
        WHERE (NOT vendor_order.item OR vendor_order.item IS NULL)
          AND vendor_order.item_no != '' AND vendor_order.item_no IS NOT NULL
          AND vendor_order.item_no = item.code";
  $db->query($q)
    or die_query($db, $q);
}

# Make sure all the items are active
$q= "UPDATE item, vendor_order
        SET item.active = 1
      WHERE vendor_order.item = item.id";
$db->query($q)
  or die_query($db, $q);
echo "- Activated ", $db->affected_rows, " items from order.\n";

# Make sure we know all the barcodes
$q= "INSERT IGNORE INTO barcode (item, code, quantity)
     SELECT item,
            REPLACE(REPLACE(barcode, 'E-', ''), 'U-', '') AS code,
            1 AS quantity
      FROM vendor_order
     WHERE item AND barcode != ''";
$db->query($q)
  or die_query($db, $q);
echo "- Loaded ", $db->affected_rows, " new barcodes from order.\n";

# Link items to vendor items if they aren't already
$q= "UPDATE vendor_item, vendor_order
        SET vendor_item.item = vendor_order.item
      WHERE NOT vendor_item.item
        AND vendor_item.code = vendor_order.item_no
        AND vendor_item.active";
$db->query($q)
  or die_query($db, $q);
echo "- Linked ", $db->affected_rows, " items to vendor items.\n";

# Add items to order
$q= "INSERT INTO txn_line (txn, item, ordered, allocated, retail_price)
     SELECT $txn_id txn, item,
            ordered, shipped, net
       FROM vendor_order
      WHERE (shipped OR backordered) AND (item != 0 AND item IS NOT NULL)";
$db->query($q)
  or die_query($db, $q);

echo "- Loaded ", $db->affected_rows, " rows into purchase order.\n";

$db->commit()
  or die_jsonp($db->error);

$q= "SELECT CAST((SUM(shipped) / SUM(ordered)) * 100 AS DECIMAL(9,1))
       FROM vendor_order";
$item_rate= $db->get_one($q);
$q= "SELECT CAST((SUM(shipped > 0) / SUM(ordered > 0)) * 100 AS DECIMAL(9,1))
       FROM vendor_order";
$sku_rate= $db->get_one($q);
echo "- Fill rate by item: $item_rate%, by SKU: $sku_rate%.\n";

$out= ob_get_contents();
ob_end_clean();

$db->query("INSERT INTO note
               SET kind = 'txn', attach_id = $txn_id,
                   content = '" . $db->escape($out) . "'")
  or die_jsonp($db->error);

$txn= txn_load_full($db, $txn_id);
$txn['result']= $out;
echo jsonp($txn);
