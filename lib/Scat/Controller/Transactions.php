<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Transactions {
  private $view, $txn, $data, $tax;

  public function __construct(View $view, \Scat\Service\Txn $txn,
                              \Scat\Service\Data $data,
                              \Scat\Service\Tax $tax)
  {
    $this->view= $view;
    $this->txn= $txn;
    $this->data= $data;
    $this->tax= $tax;
  }

  private function getAmazonClient(\Scat\Service\Config $config) {
    $config= [
      'merchant_id' => $config->get('amazon.merchant_id'),
      'access_key' => $config->get('amazon.access_key'),
      'secret_key' => $config->get('amazon.secret_key'),
      'client_id' => $config->get('amazon.client_id'),
      'region' => $config->get('amazon.region'),
      'currency_code' => $config->get('amazon.currency_code'),
      'sandbox' => (bool)$GLOBALS['DEBUG']
    ];

    $client= new \AmazonPay\Client($config);
    return $client;
  }

  public function search(Request $request, Response $response, $type) {
    $q= trim($request->getParam('q'));

    if (preg_match('/^((%V|@)INV-)?(\d+)/', $q, $m)) {
      $txn= $this->txn->fetchById($m[3]);
      if ($txn) {
        return $response->withRedirect(
          ($type == 'customer' ? '/sale/' : '/purchase/') . $txn->id
        );
      }
    }

    $page= (int)$request->getParam('page');
    $limit= 25;

    $txns= $this->txn->find($type, $page, $limit, $q);
    if (($status= $request->getParam('status'))) {
      $txns= $txns->where('status', $status);
    }

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($txns->find_many());
    }

    return $this->view->render($response, 'txn/index.html', [
      'type' => $type,
      'txns' => $txns->find_many(),
      'page' => $page,
      'limit' => $limit,
      'status' => $status,
      'q' => $q,
    ]);
  }

  public function sales(Request $request, Response $response) {
    return $this->search($request, $response, 'customer');
  }

  public function newSale(Response $response) {
    ob_start();
    include "../old-index.php";
    $content= ob_get_clean();
    return $this->view->render($response, 'sale/old-new.html', [
      'title' => $GLOBALS['title'],
      'content' => $content,
    ]);
  }

  public function sale(Request $request, Response $response, $id) {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($txn);
    }

    if (!$GLOBALS['DEBUG']) {
      return $response->withRedirect("/?id=$id");
    }

    if (($block= $request->getParam('block'))) {
      $html= $this->view->fetchBlock('sale/txn.html', $block, [
        'txn' => $txn,
      ]);

      $response->getBody()->write($html);
      return $response;
    }

    return $this->view->render($response, 'sale/txn.html', [
      'txn' => $txn,
    ]);
  }

  public function createSale(Request $request, Response $response)
  {
    $this->data->beginTransaction();

    $copy_from_id= $request->getParam('copy_from_id');
    $copy= $copy_from_id ? $this->txn->fetchById($copy_from_id) : null;

    $sale= $this->txn->create('customer', [
      'tax_rate' => $this->tax->default_rate,
    ]);

    if ($copy) {
      // Just copy a limited number of fields
      foreach ([
        'person_id', 'shipping_address_id', 'tax_rate',
        'returned_from_id', 'no_rewards'
      ] as $field) {
        $sale->set($field, $copy->$field);
      }
    }

    $sale->save();

    if ($copy) {
      foreach ($copy->items()->find_many() as $line) {
        $new= $sale->items()->create();
        $data= $line->as_array();
        unset($data['id']); // don't copy id!
        unset($data['sale_price']); // or sale_price
        $new->set($data);
        $new->txn_id= $sale->id;
        $new->save();
      }
    }

    /* We don't copy notes. */

    $this->data->commit();

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      $response= $response->withStatus(201)
                          ->withHeader('Location', '/sale/' . $sale->id);
      return $response->withJson($sale);
    }

    return $response->withRedirect('/sale/' . $sale->id);
  }

  public function updateSale(Request $request, Response $response,
                              \Scat\Service\Ordure $ordure, $id)
  {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $changed= [];

    foreach ($txn->getFields() as $field) {
      if ($field == 'id') continue;
      $value= $request->getParam($field);
      if ($field == 'tax_rate' && $value == 'def') {
        $value= $this->tax->default_rate;
      }
      if ($value !== null && $value != $txn->get($field)) {
        $changed[$field]++;
        $txn->set($field, $value);
      }
    }

    if ($changed['tax_rate']) {
      $txn->recalculateTax($this->tax);
    }

    // Pass along status change to Ordure when shipping
    if ($changed['status'] && $txn->online_sale_id) {
      if (in_array($txn->status,
                    [ 'readyforpickup', 'shipping', 'shipped', 'complete']))
      {
        $ordure->markOrderShipped($txn->uuid);
      }
    }

    $txn->save();

    return $response->withJson($txn);
  }

  /* Items (aka lines) */
  public function addItem(Request $request, Response $response,
                          \Scat\Service\Catalog $catalog, $id)
  {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (!in_array($txn->status, [ 'new', 'filled', 'template' ])) {
      throw new \Scat\Exception\HttpConflictException($request,
        "Unable to add item to transaction because it is {$txn->status}."
      );
    }

    if ($request->getUploadedFiles()) {
      return $this->handleUploadedItems($request, $response, $txn);
    }

    $item_id= $request->getParam('item_id');
    $item= $catalog->getItemById($item_id);
    if (!$item) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $this->data->beginTransaction();

    $unique= preg_match('/^ZZ-(frame|print|univ|canvas|stretch|float|panel|giftcard)/i', $item->code);

    if (!$unique) {
      $line=
        $txn->items()
            ->where('item_id', $item->id)
            ->where_null('kit_id') /* Don't include kit items */
            ->find_one();
    }

    if ($unique || !$line) {
      $line= $txn->items()->create();
      $line->txn_id= $txn->id;
      $line->item_id= $item->id;

      /* Get pricing for vendor items */
      if ($txn->type == 'vendor') {
        // default to full retail
        $line->retail_price= $item->retail_price;

        if ($txn->person_id) {
          $vendor_item=
            $txn->person()->items()->where('item_id', $item->id)->find_one();
          if ($vendor_item) {
            if ($vendor_item->promo_price > 0) {
              /* Sometimes promo_price > net_price */
              $line->retail_price=
                min($vendor_item->net_price, $vendor_item->promo_price);
            } else {
              $line->retail_price= $vendor_item->net_price;
            }
          }
        }

      } else {
        $line->retail_price= $item->retail_price;
        $line->discount= $item->discount;
        $line->discount_type= $item->discount_type;
      }

      $line->taxfree= $item->taxfree;
      $line->tic= $item->tic;
    }

    $quantity= $request->getParam('quantity') ?: 1;

    $line->ordered+= $quantity * ($txn->type == 'customer') ? -1 : 1;

    $line->save();

    /* Is this a kit? Need to add kit items or adjust quantities */
    if ($item->is_kit) {
      $this->updateKitQuantities($txn, $line, $item);
    }

    // txn no longer filled?
    if ($txn->status == 'filled') {
      $txn->status= 'new';
      $txn->filled= NULL;
      $txn->save();
    }

    $txn->applyPriceOverrides($catalog);
    $txn->recalculateTax($this->tax);

    // TODO push new price to pole

    $this->data->commit();

    $line->reload();

    return $response->withJson($line);
  }

  // XXX this assumes kit contents haven't changed
  public function updateKitQuantities($txn, $line, $item) {
    foreach ($item->kit_items()->find_many() as $kit_item) {
      $kit_line= null;
      if ($line->id) {
        $kit_line= $txn->items()
          ->where('item_id', $kit_item->item_id)
          ->where('kit_id', $item->id)
          ->find_one();
      }
      if (!$kit_line) {
        $kit_line= $txn->items()->create();
        $kit_line->txn_id= $txn->id;
        $kit_line->kit_id= $item->id;
        $kit_line->item_id= $kit_item->item_id;
        $kit_line->retail_price= 0.00;
        $kit_line->tax= 0.00;
      }

      $kit_line->ordered= $line->ordered * $kit_item->quantity;

      $kit_line->save();
    }
  }

  public function handleUploadedItems(Request $request, Response $response,
                                      $txn)
  {
    foreach ($request->getUploadedFiles() as $file) {
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
      } elseif (preg_match('/K.*\\.xls/i', $_FILES['src']['name'])) {
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

      } elseif (preg_match('/^,Name,MSRP/', $line)) {
        // CSV
        $q= "LOAD DATA LOCAL INFILE '$tmpfn'
             INTO TABLE vendor_order
             FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"'
             IGNORE 1 LINES
             (item_no, description, @msrp, @sale, @net, @qty, @ext, barcode)
             SET ordered = @qty, shipped = @qty,
                 msrp = REPLACE(@msrp, '$', ''), net = REPLACE(@net, '$', '')";
        $this->data->execute($q);

      } elseif (preg_match('/^code\tqty/', $line)) {
        // Order file
        $q= "LOAD DATA LOCAL INFILE '$tmpfn'
             INTO TABLE vendor_order
             FIELDS TERMINATED BY '\t' OPTIONALLY ENCLOSED BY '\"'
             IGNORE 1 LINES
             (item_no, @qty)
             SET sku = item_no, ordered = @qty, shipped = @qty";
        $this->data->execute($q);

      } elseif (($json= json_decode(file_get_contents($tmpfn)))) {
        // JSON
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
        // MacPherson's order
        $q= "LOAD DATA LOCAL INFILE '$tmpfn'
             INTO TABLE vendor_order
             CHARACTER SET 'latin1'
             FIELDS TERMINATED BY '\t' OPTIONALLY ENCLOSED BY '\"'
             IGNORE 1 LINES
             (line, status, item_no, sku, cust_item, description, ordered,
              shipped, backordered, msrp, discount, net, unit, ext, barcode,
              account_no, po_no, order_no, bo_no, invoice_no, box_no)";
        $this->data->execute($q);

        /* Fix quantities on backorders */
        $q= "SELECT SUM(shipped + backordered) AS ordered
               FROM vendor_order
              WHERE IFNULL(unit,'') != 'AS'";
        $ordered=
          $this->data->for_table('vendor_order')->raw_query($q)->find_one();

        if (!$ordered->ordered) {
          $this->data->execute("UPDATE vendor_order SET backordered = ordered");
        }
      }

      $this->data->beginTransaction();

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

      $this->data->commit();
    }

    return $response->withJson($txn);
  }

  public function updateItem(Request $request, Response $response,
                              \Scat\Service\Catalog $catalog,
                              $id, $line_id)
  {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (!$request->getParam('force') &&
        !in_array($txn->status, [ 'new', 'filled' ])) {
      throw new \Scat\Exception\HttpConflictException($request,
        "Unable to modify item because transaction is {$txn->status}."
      );
    }

    $line= $txn->items()->find_one($line_id);
    if (!$line)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $this->data->beginTransaction();

    foreach ($line->getFields() as $field) {
      if ($field == 'id') continue;
      $value= $request->getParam($field);
      if ($value !== null) {
        $line->set($field, $value);
      }
    }

    // Have to handle this here because it depends on $txn
    $quantity= $request->getParam('quantity');
    if (strlen($quantity)) {
      /* special case: #/# lets us split line with two quantities */
      if (preg_match('!^(\d+)/(\d+)$!', $quantity, $m)) {
        $quantity= (int)$m[2] * ($txn->type == 'customer' ? -1 : 1);

        $new= $txn->items()->create();
        $new->txn_id= $txn->id;
        $new->item_id= $line->item_id;
        $new->ordered= $quantity;
        $new->retail_price= $line->retail_price;
        $new->discount_type= $line->discount_type;
        $new->discount= $line->discount;
        $new->discount_manual= $line->discount_manual;
        $new->taxfree= $line->taxfree;
        $new->save();

        $quantity= (int)$m[1];
      } else {
        $quantity= (int)$quantity;
      }

      $mul= ($txn->type == 'customer' ? -1 : 1);
      $line->ordered= $mul * $quantity;

      $item= $line->item();
      if ($item->is_kit) {
        $this->updateKitQuantities($txn, $line, $item);
      }
    }

    $line->save();

    // txn no longer filled?
    if ($txn->status == 'filled') {
      $txn->status= 'new';
      $txn->filled= NULL;
      $txn->save();
    }

    if (in_array($txn->status, [ 'new', 'filled' ])) {
      $txn->applyPriceOverrides($catalog);
      $txn->recalculateTax($this->tax);
    }

    $this->data->commit();

    return $response->withJson($line);
  }

  public function removeItem(Request $request, Response $response,
                              \Scat\Service\Catalog $catalog,
                              $id, $line_id)
  {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (!$request->getParam('force') &&
        !in_array($txn->status, [ 'new', 'filled' ])) {
      throw new \Scat\Exception\HttpConflictException($request,
        "Unable to remove item because transaction is {$txn->status}."
      );
    }

    $line= $txn->items()->find_one($line_id);
    if (!$line)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $this->data->beginTransaction();

    if ($line->item()->is_kit) {
      $txn->items()->where('kit_id', $line->item_id)->delete_many();
    }

    $line->delete();

    if (in_array($txn->status, [ 'new', 'filled' ])) {
      $txn->applyPriceOverrides($catalog);
      $txn->recalculateTax($this->tax);
    }

    $this->data->commit();

    return $response->withJson(true);
  }

  /* Payments */
  public function payments(Request $request, Response $response,
                            $id, $payment_id= null)
  {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $payment= $payment_id ? $txn->payments()->find_one($payment_id) : null;
    if ($payment_id && !$payment)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      $method= $request->getParam('method') ?: 'choose';
      return $this->view->render($response, 'dialog/pay-' . $method . '.html', [
        'txn' => $txn,
        'payment' => $payment,
      ]);
    }

    return $response->withJson($payment);
  }


  public function addPayment(Request $request, Response $response,
                              \Scat\Service\PayPal $paypal,
                              \Scat\Service\Config $config, $id)
  {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $this->data->beginTransaction();

    $method= $request->getParam('method');
    $amount= $request->getParam('amount');

    switch ($method) {
    case 'amazon':
      if ($amount <= 0) {
        throw new \Exception("Can only handle refunds.");
      }
      if (!$txn->returned_from_id) {
        throw new \Exception("Can't find original transaction to refund.");
      }

      $original= $txn->returned_from();
      $original_payment= null;

      foreach ($original->payments()->find_many() as $pay) {
        if ($pay->method == 'amazon') {
          $original_payment= $pay;
          break;
        }
      }

      if (!$original_payment) {
        throw new \Exception("Unable to find Amazon payment on original transaction");
      }

      $charge= json_decode($original_payment->data);

      $amazon= $this->getAmazonClient($config);

      // we only have the authorization here
      error_log("getting details for {$charge->AmazonAuthorizationId}\n");
      $authorization= $amazon->getAuthorizationDetails([
        'amazon_authorization_id' => $charge->AmazonAuthorizationId,
      ]);
      $details= $authorization->toArray();

      if (!$amazon->success) {
        error_log("Amazon FAIL: " . json_encode($details) . "\n");
        throw new \Exception("An unexpected Amazon error occured.");
      }

      $refund= $amazon->refund([
        // XXX We assume only one capture per authorization
        'amazon_capture_id' => $details['GetAuthorizationDetailsResult']
                                        ['AuthorizationDetails']
                                        ['IdList']
                                        ['member'],
        'refund_reference_id' => uniqid(),
        'refund_amount' => $amount,
      ]);
      $details= $refund->toArray();

      if (!$amazon->success) {
        error_log("Amazon FAIL: " . json_encode($details) . "\n");
        throw new \Exception("An unexpected Amazon error occured.");
      }

      $payment= $txn->payments()->create();
      $payment->method= 'amazon';
      $payment->txn_id= $txn->id();
      $payment->amount= -$amount;
      $payment->data= json_encode($refund->toArray());
      $payment->set_expr('processed', 'NOW()');
      $payment->save();

      break;

    case 'paypal':
      if ($amount <= 0) {
        throw new \Exception("Can only handle refunds.");
      }
      if (!$txn->returned_from_id) {
        throw new \Exception("Can't find original transaction to refund.");
      }

      $original= $txn->returned_from();
      $original_payment= null;

      foreach ($original->payments()->find_many() as $pay) {
        if ($pay->method == 'paypal') {
          $original_payment= $pay;
          break;
        }
      }

      if (!$original_payment) {
        throw new \Exception("Unable to find PayPal payment on original transaction");
      }

      $charge= json_decode($original_payment->data);

      $capture_id= $charge->purchase_units[0]->payments->captures[0]->id;

      $res= $paypal->refund($capture_id, $amount);

      $payment= $txn->payments()->create();
      $payment->method= 'paypal';
      $payment->txn_id= $txn->id();
      $payment->amount= -$amount;
      $payment->data= json_encode($res->result);
      $payment->set_expr('processed', 'NOW()');
      $payment->save();

      break;

    case 'stripe':
      if ($amount <= 0) {
        throw new \Exception("Can only handle refunds.");
      }
      if (!$txn->returned_from_id) {
        throw new \Exception("Can't find original transaction to refund.");
      }

      $original= $txn->returned_from();
      $original_payment= null;

      foreach ($original->payments()->find_many() as $pay) {
        if ($pay->method == 'stripe') {
          $original_payment= $pay;
          break;
        }
      }

      if (!$original_payment) {
        throw new \Exception("Unable to find Stripe payment on original transaction");
      }

      $charge= json_decode($original_payment->data);

      $stripe= new \Stripe\StripeClient($config->get('stripe.secret_key'));

      $refund= $stripe->refunds->create([
        'charge' => $charge->charge_id,
        'amount' => (integer)(new \Decimal\Decimal(100) * $amount),
      ]);

      $payment= $txn->payments()->create();
      $payment->method= 'stripe';
      $payment->txn_id= $txn->id();
      $payment->amount= -$amount;
      $payment->data= json_encode($refund);
      $payment->set_expr('processed', 'NOW()');
      $payment->save();

      break;

    default:
      throw new \Exception("Don't know how to handle a '$method' payment");
    }

    if ($txn->total() == $txn->total_paid()) {
      $txn->set_expr('paid', 'NOW()');
      if (in_array($txn->status, [ 'new', 'filled' ])) {
        $txn->status= 'complete';
      }
    } else {
      $txn->paid= NULL;
      $txn->status= 'new'; // not right, what about filled?
    }
    $txn->save();

    $this->data->commit();

    $txn->reload();

    return $response->withJson($txn);
  }


  public function emailForm(Request $request, Response $response, $id) {
    $txn= $this->txn->fetchById($id);

    $data= [];
    if (($canned= $request->getParam('canned'))) {
      $data= $this->data->factory('CannedMessage')
        ->where('slug', $canned)
        ->find_one();
      $data= $data->as_array();
    }

    $data['txn']= $txn;

    return $this->view->render($response, 'dialog/email-invoice.html', $data);
  }

  public function email(Request $request, Response $response, $id,
                        \Scat\Service\Email $email)
  {
    $txn= $this->txn->fetchById($id);

    $to_name= $request->getParam('name');
    $to_email= $request->getParam('email');
    $subject= trim($request->getParam('subject'));

    $body= $this->view->fetch('email/invoice.html', [
      'txn' => $txn,
      'subject' => $subject,
      'content' =>
        $request->getParam('content'),
    ]);

    $attachments= [];
    if ($request->getParam('include_details')) {
      $pdf= $txn->getInvoicePDF();
      $attachments[]= [
        base64_encode($pdf->Output('', 'S')),
        'application/pdf',
        (($txn->type == 'vendor') ? 'PO' : 'I') .
          $txn->formatted_number() . '.pdf',
        'attachment'
      ];
    }

    $res= $email->send([ $to_email => $to_name ],
                       $subject, $body, $attachments);

    if (($status= $request->getParam('new_status'))) {
      $txn->set('status', $status);
      $txn->save();
    }

    return $response->withJson([ "message" => "Email sent." ]);
  }

  /* Shipping address */
  public function shippingAddress(Request $request, Response $response, $id)
  {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $this->view->render($response, 'dialog/address.html', [
        'txn' => $txn,
      ]);
    }

    return $response->withJson($txn->address());
  }

  public function updateShippingAddress(Request $request, Response $response,
                                        \Scat\Service\Shipping $shipping,
                                        $id)
  {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $this->data->beginTransaction();

    $details= $request->getParams();

    if ($details['pickup'] == 1) {
      $txn->shipping_address_id= 1;
    } else {
      $details['verify']= [ 'delivery' ];
      $easypost_address= $shipping->createAddress($details);

      /* We always create a new address. */
      $address= $this->data->factory('Address')->create();
      $address->easypost_id= $easypost_address->id;
      $address->name= $easypost_address->name;
      $address->company= $easypost_address->company;
      $address->street1= $easypost_address->street1;
      $address->street2= $easypost_address->street2;
      $address->city= $easypost_address->city;
      $address->state= $easypost_address->state;
      $address->zip= $easypost_address->zip;
      $address->country= $easypost_address->country;
      $address->phone= $easypost_address->phone;
      $address->timezone=
        $easypost_address->verifications->delivery->details->time_zone;
      $address->save();

      $txn->shipping_address_id= $address->id;
    }

    $txn->save();

    $this->data->commit();

    return $response->withJson($address);
  }

  /* Shipments */

  public function saleShipments(Request $request, Response $response,
                                \Scat\Service\Shipping $shipping,
                                $id, $shipment_id= null)
  {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $shipment= $shipment_id ? $txn->shipments()->find_one($shipment_id) : null;
    if ($shipment_id && !$shipment)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      $dialog= ($request->getParam('tracker') ?
                'dialog/tracker.html' :
                'dialog/shipment.html');
      return $this->view->render($response, $dialog, [
        'txn' => $txn,
        'shipment' => $shipment,
        'easypost' =>
          $shipment ? $shipping->getShipment($shipment) : null,
      ]);
    }

    return $response->withJson($shipment);
  }

  public function trackShipment(Request $request, Response $response,
                                \Scat\Service\Shipping $shipping,
                                $id, $shipment_id)
  {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $shipment= $shipment_id ? $txn->shipments()->find_one($shipment_id) : null;
    if ($shipment_id && !$shipment)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (!$shipment->tracker_id)
      throw new \Slim\Exception\HttpNotFoundException($request,
        "No tracker_id found for that shipment.");

    $tracker_url= $shipping->getTrackerUrl($shipment);

    return $response->withRedirect($tracker_url);
  }

  public function printShipmentLabel(Request $request, Response $response,
                                      \Scat\Service\Printer $print,
                                      \Scat\Service\Shipping $shipping,
                                      $id, $shipment_id)
  {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $shipment= $shipment_id ? $txn->shipments()->find_one($shipment_id) : null;
    if ($shipment_id && !$shipment)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (!$shipment->method_id)
      throw new \Slim\Exception\HttpNotFoundException($request,
        "No details found for that shipment.");

    $details= $shipping->getShipment($shipment);

    $PNG= 1;

    if ($PNG) {
      $png= file_get_contents($details->postage_label->label_url);

      return $print->printPNG($response, 'shipping-label', $png);
    }

    if ($ZPL) {
      if (!$details->postage_label->label_zpl_url) {
        $details->label([ 'file_format' => 'zpl' ]);
      }

      $zpl= file_get_contents($details->postage_label->label_zpl_url);

      return $print->printZPL($response, 'shipping-label', $zpl);
    }

    if (!$details->postage_label->label_pdf_url) {
      $details->label([ 'file_format' => 'pdf' ]);
    }

    $pdf= file_get_contents($details->postage_label->label_pdf_url);

    return $print->printPDF($response, 'shipping-label', $pdf);
  }

  public function updateShipment(Request $request, Response $response,
                                  \Scat\Service\Shipping $shipping,
                                  $id, $shipment_id= null)
  {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $shipment= $shipment_id ? $txn->shipments()->find_one($shipment_id) : null;
    if ($shipment_id && !$shipment)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if (!$shipment) {
      $shipment= $txn->shipments()->create();
      $shipment->txn_id= $txn->id;
    }

    foreach ($shipment->getFields() as $field) {
      if ($field == 'id') continue;
      $value= $request->getParam($field);
      if ($value !== null) {
        $shipment->set($field, $value);
      }
    }

    /* New tracking code? */
    if (($tracking_code= $request->getParam('tracking_code'))) {
      $shipment->method= $request->getParam('method');
      $shipment->tracker_id= $shipping->createTracker(
        $shipment->method,
        $tracking_code,
        $request->getParam('carrier'),
      );
      /* Wait for webhook to update status. */
      $shipment->status= 'unknown';
    }

    /* New package info? */
    if (($weight= $request->getParam('weight'))) {
      list($length, $width, $height)=
        preg_split('/[^\d.]+/', trim($request->getParam('dimensions')));
      if (preg_match('/([0-9.]+\s+)?([0-9.]+) *oz/', $weight, $m)) {
        $weight= (int)$m[1] + ($m[2] / 16);
      }

      $parcel= [
        'weight' => $weight * 16, // Needs to be oz.
        'length' => $length,
        'width' => $width,
        'height' => $height,
      ];
      $predefined_package= $request->getParam('predefined_package');
      if (strlen($predefined_package)) {
        $parcel['predefined_package']= $predefined_package;
      }

      $options= [
        'invoice_number' => $txn->formatted_number(),
        'label_date' => $request->getParam('label_date'),
      ];
      if ($request->getParam('hazmat')) {
        $options['hazmat']= 'LIMITED_QUANTITY';
      }
      if ($request->getParam('signature')) {
        $options['delivery_confirmation']= 'SIGNATURE';
      }

      $extra= $shipping->createShipment([
        'from_address' => $shipping->getDefaultFromAddress(),
        'to_address' =>
          $shipping->retrieveAddress($txn->shipping_address()->easypost_id),
        'parcel' => $parcel,
        'options' => $options,
      ]);

      $shipment->weight= $weight;
      if ($shipment->length) {
        $shipment->length= $length;
        $shipment->width= $width;
        $shipment->height= $height;
      }

      $shipment->method_id= $extra->id;
      $shipment->status= 'pending';
    }

    /* Re-rate? */
    if (($rerate= $request->getParam('rerate'))) {
      $ep= $shipping->getShipment($shipment);
      $ep->get_rates();

      $shipment->status= 'pending';
    }

    /* Select a rate? */
    if (($rate_id= $request->getParam('rate_id'))) {
      $ep= $shipping->getShipment($shipment);
      $details= [ 'rate' => [ 'id' => $rate_id ] ];
      $insurance= $txn->subtotal();
      if ($insurance > 100.00) {
        $details['insurance']= $insurance;
      }

      $res= $ep->buy($details);

      $shipment->carrier= $res->selected_rate->carrier;
      $shipment->service= $res->selected_rate->service;
      $shipment->rate= $res->selected_rate->rate;
      $shipment->insurance= $res->insurance;

      $shipment->status= 'unknown';
      $shipment->tracker_id= $ep->tracker->id;
    }

    if (!$shipment->method_id && !$shipment->tracker_id) {
      throw new \Exception("Not enough information to create shipment.");
    }

    $shipment->save();

    return $response->withJson($shipment);
  }

  /* Drop-ships */
  public function saleDropShips(Request $request, Response $response,
                                $id, $dropship_id= null)
  {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $dropship= $dropship_id ? $txn->dropships()->find_one($dropship_id) : null;
    if ($dropship_id && !$dropship)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      $vendors= $this->data->factory('Person')
                      ->where('active', 1)
                      ->where('role', 'vendor')
                      ->order_by_asc(['company', 'name'])
                      ->find_many();

      return $this->view->render($response, 'dialog/dropship.html', [
        'vendors' => $vendors,
        'txn' => $txn,
        'dropship' => $dropship
      ]);
    }

    return $response->withJson($dropship);
  }

  public function createDropShip(Request $request, Response $response,
                                  $id, $dropship_id= null)
  {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $dropship= $dropship_id ? $txn->dropships()->find_one($dropship_id) : null;
    if ($dropship_id && !$dropship)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $this->data->beginTransaction();

    if (!$dropship) {
      $dropship= $this->txn->create('vendor');
      $dropship->shipping_address_id= $txn->shipping_address_id;
      $dropship->returned_from_id= $txn->id;
    }

    foreach ($dropship->getFields() as $field) {
      if ($field == 'id') continue;
      $value= $request->getParam($field);
      if ($value !== null) {
        $dropship->set($field, $value);
      }
    }

    $vendor= $dropship->person();
    if (!$vendor->role == 'vendor') {
      throw new \Slim\Exception\HttpBadRequestException($request,
        "No vendor supplied for dropship!"
      );
    }

    /* New dropship? Add items that this vendor has available. */
    if (!$dropship_id) {
      foreach ($txn->items()->find_many() as $item) {
        $vi= $vendor->items()->where('item_id', $item->item_id)->find_one();
        if ($vi) {
          $new= $dropship->items()->create();
          $new->txn_id= $dropship->id;
          $new->item_id= $item->item_id;
          $new->ordered= -1 * $item->ordered;
          $new->retail_price= ($vi->promo_price > 0 && $new->ordered > $vi->promo_quantity) ? $vi->promo_price : $vi->net_price;
          $new->save();
        }
      }
    }

    $dropship->save();

    if ($txn->status == 'paid') {
      $txn->status= 'processing';
      $txn->save();
    }

    $this->data->commit();

    return $response->withJson($dropship);
  }

  public function captureTax(Request $request, Response $response, $id)
  {
    $txn= $this->txn->fetchById($id);
    if (!$txn)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if ($txn->tax_captured) {
      return $response->withJson([ 'message' => 'Already captured.' ]);
    }

    $txn->captureTax($this->tax);

    return $response->withJson($txn);
  }

  /* PURCHASES */

  public function purchases(Request $request, Response $response) {
    return $this->search($request, $response, 'vendor');
  }

  public function reorderForm(Request $request, Response $response) {
    $extra= $extra_field= $extra_field_name= '';

    $all= (int)$request->getParam('all');

    $vendor_code= "NULL";
    $vendor_id= (int)$request->getParam('vendor_id');
    if ($vendor_id > 0) {
      $vendor_code= "(SELECT code FROM vendor_item WHERE vendor_id = $vendor_id AND item_id = item.id AND vendor_item.active LIMIT 1)";
      $extra= "AND EXISTS (SELECT id
                             FROM vendor_item
                            WHERE vendor_id = $vendor_id
                              AND item_id = item.id
                              AND vendor_item.active)";
      $extra_field= "(SELECT MIN(IF(promo_quantity, promo_quantity,
                                    purchase_quantity))
                        FROM vendor_item
                       WHERE item_id = item.id
                         AND vendor_id = $vendor_id
                         AND vendor_item.active)
                      AS minimum_order_quantity,
                     (SELECT MIN(vendor_item.id)
                        FROM vendor_item
                        JOIN person ON vendor_item.vendor_id = person.id
                      WHERE item_id = item.id
                        AND vendor_id = $vendor_id
                        AND vendor_item.active)
                      AS vendor_item_id,
                     (SELECT MIN(IF(promo_price, promo_price, net_price))
                        FROM vendor_item
                        JOIN person ON vendor_item.vendor_id = person.id
                      WHERE item_id = item.id
                        AND vendor_id = $vendor_id
                        AND vendor_item.active)
                      AS cost,
                     (SELECT MIN(IF(promo_price, promo_price, net_price)
                                 * ((100 - vendor_rebate) / 100))
                        FROM vendor_item
                        JOIN person ON vendor_item.vendor_id = person.id
                      WHERE item_id = item.id
                        AND vendor_id = $vendor_id
                        AND vendor_item.active) -
                     (SELECT MIN(IF(promo_price, promo_price, net_price)
                                 * ((100 - vendor_rebate) / 100))
                        FROM vendor_item
                        JOIN person ON vendor_item.vendor_id = person.id
                       WHERE item_id = item.id
                         AND NOT special_order
                         AND vendor_id != $vendor_id
                         AND vendor_item.active)
                     cheapest, ";
      $extra_field_name= "minimum_order_quantity, vendor_item_id, cheapest, cost,";
    } else if ($vendor_id < 0) {
      // No vendor
      $extra= "AND NOT EXISTS (SELECT id
                                 FROM vendor_item
                                WHERE item_id = item.id
                                  AND vendor_item.active)";
    }

    $code= trim($request->getParam('code'));
    if ($code) {
      $extra.= " AND code LIKE " . $this->data->escape($code.'%');
    }
    $criteria= ($all ? '1=1'
                     : '(ordered IS NULL OR NOT ordered)
                        AND IFNULL(stock, 0) < minimum_quantity');
    $q= "SELECT id, code, vendor_code, name, stock,
                minimum_quantity, last3months,
                $extra_field_name
                order_quantity
           FROM (SELECT item.id,
                        item.code,
                        $vendor_code AS vendor_code,
                        name,
                        SUM(allocated) stock,
                        minimum_quantity,
                        (SELECT -1 * SUM(allocated)
                           FROM txn_line JOIN txn ON (txn_id = txn.id)
                          WHERE type = 'customer'
                            AND txn_line.item_id = item.id
                            AND filled > NOW() - INTERVAL 3 MONTH)
                        AS last3months,
                        (SELECT SUM(ordered - allocated)
                           FROM txn_line JOIN txn ON (txn_id = txn.id)
                          WHERE type = 'vendor'
                            AND txn_line.item_id = item.id
                            AND created > NOW() - INTERVAL 12 MONTH)
                        AS ordered,
                        $extra_field
                        IF(minimum_quantity > minimum_quantity - SUM(allocated),
                           minimum_quantity,
                           minimum_quantity - IFNULL(SUM(allocated), 0))
                          AS order_quantity
                   FROM item
                   LEFT JOIN txn_line ON (item_id = item.id)
                  WHERE purchase_quantity
                    AND item.active AND NOT item.deleted
                    $extra
                  GROUP BY item.id
                  ORDER BY code) t
           WHERE $criteria
           ORDER BY code
          ";

    $this->data->configure('logging', false);
    $items= $this->data->for_table('item')->raw_query($q)->find_many();
    $this->data->configure('logging', true);

    return $this->view->render($response, 'purchase/reorder.html', [
      'items' => $items,
      'all' => $all,
      'code' => $code,
      'vendor_id' => $vendor_id,
      'person' => $this->data->factory('Person')->find_one($vendor_id)
    ]);
  }

  public function createPurchase(Request $request, Response $response) {
    $vendor_id= $request->getParam('vendor_id');

    error_log("Creating purchase for $vendor_id");

    if (!$vendor_id) {
      throw new \Exception("No vendor specified.");
    }

    $purchase= $this->txn->create('vendor', [
      'person_id' => $vendor_id,
      'tax_rate' => 0,
    ]);

    $purchase->save();

    /* Pass through to addToPurchase() to handle adding items */
    return $this->addToPurchase($request, $response, $purchase->id);
  }

  public function addToPurchase(Request $request, Response $response, $id) {
    $purchase= $this->txn->fetchById($id);
    if (!$purchase) {
      throw new \Exception("Unable to find transaction.");
    }

    $this->data->beginTransaction();

    $vendor_id= $purchase->person_id;

    if (!$vendor_id) {
      throw new \Exception("No vendor specified.");
    }

    $items= $request->getParam('item');
    if ($items) {
      foreach ($items as $item_id => $quantity) {
        if (!$quantity) {
          continue;
        }

        // TODO should be using Catalog Service for this
        $vendor_items=
          \Scat\Model\VendorItem::findByItemIdForVendor($item_id,
                                                  $vendor_id);

        // Get the lowest available price for our quantity
        $price= 0;
        foreach ($vendor_items as $item) {
          $contender= ($item->promo_price > 0.00 &&
                       $quantity >= $item->promo_quantity) ?
                      $item->promo_price :
                      (($quantity >= $item->purchase_quantity) ?
                       $item->net_price :
                       0);
          $price= ($price && $price < $contender) ?
                  $price :
                  $contender;
        }

        if (!$price) {
          error_log("Failed to get price for $item_id");
          // Just use the first one we found
          $price= $vendor_items[0]->net_price;
        }

        $item= $purchase->items()->create();
        $item->txn_id= $purchase->id;
        $item->item_id= $item_id;
        $item->ordered= $quantity;
        $item->retail_price= $price;
        $item->save();
      }
    }

    $this->data->commit();

    $path= '/purchase/' . $purchase->id;

    return $response->withRedirect($path);
  }

  public function purchase(Response $response, $id) {
    return $response->withRedirect("/?id=$id");
  }

}
