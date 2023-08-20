<?php
namespace Scat\Service;

class Report
{
  public function __construct(
    private Data $data,
    private Search $search
  ) {
  }

  public function brandSales($begin= null, $end= null, $items= null) {
    $sql_criteria= "1=1";
    if ($items) {
      list($sql_criteria, $x)= $this->search->buildSearchItemsWhere($items);
    }

    /* Current */
    $q= "CREATE TEMPORARY TABLE current
           (item_id INT UNSIGNED PRIMARY KEY,
            brand_id INT UNSIGNED NOT NULL,
            units INT NOT NULL,
            amount DECIMAL(9,2) NOT NULL,
            KEY (brand_id))
         SELECT
                txn_line.item_id, IFNULL(brand.id, 0) brand_id,
                SUM(-1 * allocated) units,
                SUM(-1 * allocated * sale_price(txn_line.retail_price,
                                                txn_line.discount_type,
                                                txn_line.discount)) amount
           FROM txn
           LEFT JOIN txn_line ON txn.id = txn_line.txn_id
                JOIN item ON txn_line.item_id = item.id
           LEFT JOIN barcode ON item.id = barcode.item_id
           LEFT JOIN product ON product_id = product.id
           LEFT JOIN brand ON product.brand_id = brand.id
           LEFT JOIN department ON department.id = product.department_id
          WHERE type = 'customer'
            AND ($sql_criteria)
            AND filled BETWEEN ? AND ? + INTERVAL 1 DAY
            AND txn_line.item_id IS NOT NULL
          GROUP BY 1";

    $this->data->execute($q, [ $begin, $end ]);

    /* Previous */
    $q= "CREATE TEMPORARY TABLE previous
           (item_id INT UNSIGNED PRIMARY KEY,
            brand_id INT UNSIGNED NOT NULL,
            units INT NOT NULL,
            amount DECIMAL(9,2) NOT NULL,
            KEY (brand_id))
         SELECT
                txn_line.item_id, IFNULL(brand.id, 0) brand_id,
                SUM(-1 * allocated) units,
                SUM(-1 * allocated * sale_price(txn_line.retail_price,
                                                txn_line.discount_type,
                                                txn_line.discount)) amount
           FROM txn
           LEFT JOIN txn_line ON txn.id = txn_line.txn_id
                JOIN item ON txn_line.item_id = item.id
           LEFT JOIN barcode ON item.id = barcode.item_id
           LEFT JOIN product ON product_id = product.id
           LEFT JOIN brand ON product.brand_id = brand.id
           LEFT JOIN department ON department.id = product.department_id
          WHERE type = 'customer'
            AND ($sql_criteria)
            AND filled BETWEEN ? - INTERVAL 1 YEAR
                           AND ? + INTERVAL 1 DAY - INTERVAL 1 YEAR
            AND txn_line.item_id IS NOT NULL
          GROUP BY 1";

    $this->data->execute($q, [ $begin, $end ]);

    /* Report */
    $q= "SELECT
                name, slug, 0,
                (SELECT SUM(amount) FROM current WHERE brand_id = id)
                  AS current_amount,
                (SELECT SUM(amount) FROM previous WHERE brand_id = id)
                  AS previous_amount
           FROM brand
         HAVING current_amount OR previous_amount
          ORDER BY name";

    $sales= $this->data->for_table('item')->raw_query($q)->find_many();

    return [
      'begin' => $begin,
      'end' => $end,
      'items' => $items,
      'sales' => $sales,
    ];
  }

  public function categorySales($begin= null, $end= null, $items= null) {
    $sql_criteria= "1=1";
    if ($items) {
      list($sql_criteria, $x)= $this->search->buildSearchItemsWhere($items);
    }

    /* Current */
    $q= "CREATE TEMPORARY TABLE report_current
           (item_id INT UNSIGNED PRIMARY KEY,
            product_id INT UNSIGNED,
            department_id INT UNSIGNED,
            units INT NOT NULL,
            amount DECIMAL(9,2) NOT NULL,
            KEY (department_id))
         SELECT
                txn_line.item_id, product_id, department_id,
                SUM(-1 * allocated) units,
                SUM(-1 * allocated * sale_price(txn_line.retail_price,
                                                txn_line.discount_type,
                                                txn_line.discount)) amount
           FROM txn
           LEFT JOIN txn_line ON txn.id = txn_line.txn_id
                JOIN item ON txn_line.item_id = item.id
           LEFT JOIN barcode ON item.id = barcode.item_id
           LEFT JOIN product ON product_id = product.id
           LEFT JOIN brand ON product.brand_id = brand.id
           LEFT JOIN department ON department.id = department_id
          WHERE type = 'customer'
            AND ($sql_criteria)
            AND filled BETWEEN ? AND ? + INTERVAL 1 DAY
            AND txn_line.item_id IS NOT NULL
          GROUP BY 1";

    $this->data->execute($q, [ $begin, $end ]);

    /* Previous */
    $q= "CREATE TEMPORARY TABLE report_previous
           (item_id INT UNSIGNED PRIMARY KEY,
            product_id INT UNSIGNED,
            department_id INT UNSIGNED,
            units INT NOT NULL,
            amount DECIMAL(9,2) NOT NULL,
            KEY (department_id))
         SELECT
                txn_line.item_id, product_id, department_id,
                SUM(-1 * allocated) units,
                SUM(-1 * allocated * sale_price(txn_line.retail_price,
                                                txn_line.discount_type,
                                                txn_line.discount)) amount
           FROM txn
           LEFT JOIN txn_line ON txn.id = txn_line.txn_id
                JOIN item ON txn_line.item_id = item.id
           LEFT JOIN barcode ON item.id = barcode.item_id
           LEFT JOIN product ON product_id = product.id
           LEFT JOIN brand ON product.brand_id = brand.id
           LEFT JOIN department ON department.id = department_id
          WHERE type = 'customer'
            AND ($sql_criteria)
            AND filled BETWEEN ? - INTERVAL 1 YEAR
                           AND ? + INTERVAL 1 DAY - INTERVAL 1 YEAR
            AND txn_line.item_id IS NOT NULL
          GROUP BY 1";

    $this->data->execute($q, [ $begin, $end ]);

    /* Report */
    $q= "SELECT
                name, parent_id,
                  IF(parent_id,
                   CONCAT((SELECT slug FROM department d
                            WHERE d.id = department.parent_id), '/', slug),
                   slug)
                  AS full_slug,
                slug,
                (SELECT SUM(amount) FROM report_current WHERE department_id = id)
                  AS current_amount,
                (SELECT SUM(amount) FROM report_previous WHERE department_id = id)
                  AS previous_amount
           FROM department
         HAVING NOT parent_id OR current_amount OR previous_amount
    /*
          UNION
          SELECT
                'Unknown' AS name, 0 AS parent_id,
                ''  AS slug,
                (SELECT SUM(amount) FROM report_current
                                   WHERE department_id IS NULL)
                  AS current_amount,
                (SELECT SUM(amount) FROM report_previous
                                   WHERE department_id IS NULL)
                  AS previous_amount
    */
          ORDER BY full_slug
          ";

    $sales= $this->data->for_table('item')->raw_query($q)->find_many();

    return [
      'begin' => $begin,
      'end' => $end,
      'items' => $items,
      'sales' => $sales,
    ];
  }

  public function sales($span= '', $begin= null, $end= null) {
    if (!$begin) {
      $begin= "DATE(NOW() - INTERVAL 10 DAY)";
    } else {
      $begin= "'{$begin}'";
    }

    if (!$end) {
      $end= "DATE(NOW() + INTERVAL 1 DAY)";
    } else {
      $end= "'{$end}'";
    }

    switch ($span) {
    case 'all':
      $format= 'All';
      break;
    case 'year':
      $format= '%Y';
      break;
    case 'month':
      $format= '%Y-%m';
      break;
    case 'week':
      $format= '%X-W%v';
      break;
    case 'hour':
      $format= '%w (%a) %H:00';
      break;
    case 'day':
    default:
      $format= '%Y-%m-%d %a';
      break;
    }

    $q= "SELECT DATE_FORMAT(paid, '$format') AS span,
                SUM(taxed + untaxed) AS total,
                SUM(IF(online_sale_id IS NULL, taxed + untaxed, 0))
                  AS in_person,
                SUM(IF(online_sale_id IS NOT NULL, taxed + untaxed, 0))
                  AS online,
                SUM(IF(online_sale_id IS NOT NULL AND first, taxed + untaxed, 0))
                  AS online_first,
                SUM(IF(online_sale_id IS NOT NULL AND shipping_address_id = 1,
                        taxed + untaxed, 0)) AS pickup,
                SUM(IF(online_sale_id IS NOT NULL AND shipping_address_id > 1,
                        taxed + untaxed, 0)) AS shipped,
                SUM(IF(uuid, tax,
                       ROUND_TO_EVEN(taxed * (tax_rate / 100), 2)))
                  AS tax,
                SUM(IF(uuid, untaxed + taxed + tax,
                       ROUND_TO_EVEN(taxed * (1 + (tax_rate / 100)), 2)
                         + untaxed))
                  AS total_taxed,
                MIN(DATE(paid)) AS raw_date,
                COUNT(*) AS transactions
           FROM (SELECT
                        txn.uuid,
                        txn.online_sale_id,
                        IF(person_id,
                           (SELECT MIN(id) FROM txn b WHERE b.person_id = txn.person_id) = txn.id,
                           0)
                        AS first,
                        txn.shipping_address_id,
                        paid,
                        CAST(ROUND_TO_EVEN(
                          SUM(IF(txn_line.taxfree, 1, 0) *
                            IF(type = 'customer', -1, 1) * ordered *
                            sale_price(txn_line.retail_price,
                                       txn_line.discount_type,
                                       txn_line.discount)),
                          2) AS DECIMAL(9,2))
                        AS untaxed,
                        CAST(ROUND_TO_EVEN(
                          SUM(IF(txn_line.taxfree, 0, 1) *
                            IF(type = 'customer', -1, 1) * ordered *
                            sale_price(txn_line.retail_price,
                                       txn_line.discount_type,
                                       txn_line.discount)),
                          2) AS DECIMAL(9,2))
                        AS taxed,
                        tax_rate,
                        SUM(tax) AS tax
                   FROM txn
                   LEFT JOIN txn_line ON (txn.id = txn_line.txn_id)
                        JOIN item ON (txn_line.item_id = item.id)
                  WHERE paid IS NOT NULL
                    AND paid BETWEEN $begin AND $end
                    AND type = 'customer'
                    AND code NOT LIKE 'ZZ-gift%'
                  GROUP BY txn.id
                ) t
          GROUP BY 1
          ORDER BY 1 DESC";

    $sales= $this->data->for_table('item')->raw_query($q)->find_many();

    return [ "sales" => $sales ];
  }

  public function purchases($span= '', $begin= null, $end= null) {
    if (!$begin) {
      $begin= "DATE(NOW() - INTERVAL 10 DAY)";
    } else {
      $begin= "'{$begin}'";
    }

    if (!$end) {
      $end= "DATE(NOW() + INTERVAL 1 DAY)";
    } else {
      $end= "'{$end}'";
    }

    switch ($span) {
    case 'all':
      $format= 'All';
      break;
    case 'year':
      $format= '%Y';
      break;
    case 'month':
      $format= '%Y-%m';
      break;
    case 'week':
      $format= '%X-W%v';
      break;
    case 'hour':
      $format= '%w (%a) %H:00';
      break;
    case 'day':
    default:
      $format= '%Y-%m-%d %a';
      break;
    }

    $q= "SELECT DATE_FORMAT(created, '$format') AS span,
                MIN(DATE(created)) AS raw_date,
                SUM(total) AS total,
                COUNT(*) AS transactions
           FROM (SELECT
                        created,
                        CAST(ROUND_TO_EVEN(
                          SUM(IF(type = 'customer', -1, 1) * ordered *
                            sale_price(txn_line.retail_price,
                                       txn_line.discount_type,
                                       txn_line.discount)),
                          2) AS DECIMAL(9,2))
                        AS total
                   FROM txn
                   LEFT JOIN txn_line ON (txn.id = txn_line.txn_id)
                        JOIN item ON (txn_line.item_id = item.id)
                  WHERE
                        created BETWEEN $begin AND $end
                    AND type = 'vendor'
                  GROUP BY txn.id
                ) t
          GROUP BY 1
          ORDER BY 1 DESC";

    $purchases= $this->data->for_table('item')->raw_query($q)->find_many();

    return [ "purchases" => $purchases ];
  }

  public function dogs() {
    return [ "items" => $this->data->factory('Item')
                          ->select('*')
                          ->select_expr('(SELECT MIN(created)
                                            FROM txn
                                            JOIN txn_line ON txn.id = txn_line.txn_id
                                           WHERE txn_line.item_id = item.id
                                             AND type = "vendor")',
                                        'first_seen')
                          ->select_expr('(SELECT SUM(ordered)
                                            FROM txn_line
                                           WHERE txn_line.item_id = item.id)',
                                        'stocked')
                          ->select_expr('(SELECT MAX(paid)
                                            FROM txn
                                            JOIN txn_line ON txn.id = txn_line.txn_id
                                           WHERE txn_line.item_id = item.id
                                             AND type = "customer")',
                                        'last_sale')
                          ->where_gt('item.active', 0)
                          ->where_gt('item.minimum_quantity', 0)
                          ->having_raw('(first_seen < NOW() - INTERVAL 1 YEAR) AND
                                        (last_sale IS NULL OR last_sale < NOW() - INTERVAL 1 YEAR) AND
                                        (stocked > 0)')
                          ->order_by_asc('code')
                          ->find_many() ];
  }

  public function emptyProducts() {
    return [ "products" => $this->data->factory('Product')
                             ->select('*')
                             ->select_expr('(SELECT COUNT(*)
                                               FROM item
                                              WHERE product.id = product_id AND
                                                    item.active)',
                                           'items')
                             ->where_gt('product.active', 0)
                             ->having_equal('items', 0)
                             ->order_by_asc('name')
                             ->find_many() ];
  }

  public function backorderedItems() {
    return [ "items" => $this->data->factory('Item')
                             ->select('*')
                             ->select_expr('(SELECT SUM(allocated)
                                               FROM txn_line
                                              WHERE item.id = txn_line.item_id)',
                                           'stock')
                             ->where_gt('active', 0)
                             ->where_gt('purchase_quantity', 0)
                             ->where_not_equal('is_kit', 1)
                             ->having_lt('stock', 0)
                             ->order_by_asc('code')
                             ->find_many() ];
  }

  public function cashflow($begin= null, $end= null) {
    if (!$begin) {
      $begin= date('Y-m-d', strtotime('30 days ago'));
    }
    if (!$end) {
      $end= date('Y-m-d', strtotime('today'));
    }

    $q= "SELECT DATE_FORMAT(processed, '%Y-%m-%d %a') AS date,
                method, cc_type, SUM(amount) amount
           FROM payment
          WHERE processed BETWEEN '$begin 00:00:00' AND '$end 23:59:59'
          GROUP BY date, method, cc_type
          ORDER BY date DESC";

    $rows= $this->data->for_table('payment')->raw_query($q)->find_many();

    $data= $seen= [];
    $total= new \Decimal\Decimal(0);

    foreach ($rows as $row) {
      $method= $row->method;
      /* Treat change as cash */
      if ($method == 'change') $method= 'cash';
      $date= $row->date;
      $amount= new \Decimal\Decimal($row->amount);
      $data[$date][$method]= $amount + ($data[$date][$method] ?? 0);
      @$seen[$method]++; /* Track methods we've seen */
      /* Don't add withdrawals to total */
      if ($method != 'withdrawal') {
        $data[$date]['total']= $amount + ($data[$date]['total'] ?? 0);
        $total+= $row->amount;
      }
    }

    return [
      'begin' => $begin,
      'end' => $end,
      'methods' => array_filter(\Scat\Model\Payment::$methods,
                                fn($key) => array_key_exists($key, $seen),
                                ARRAY_FILTER_USE_KEY),
      'data' => $data,
      'total' => (string)$total,
    ];

  }

  public function itemSales($begin= null, $end= null, $items_query= null) {
    $items= $this->search->buildSearchItems($items_query);

    return [
      "begin" => $begin,
      "end" => $end,
      "items_query" => $items_query,
      "items" =>
        $items
          ->select_expr('SUM(-1 * allocated)', 'sold')
          ->select_expr('AVG(sale_price(txn_line.retail_price, txn_line.discount_type,
                                        txn_line.discount))',
                        'average')
          ->select_expr('SUM(-1 * allocated * sale_price(txn_line.retail_price,
                                                          txn_line.discount_type,
                                                          txn_line.discount))',
                        'total')
          ->join('txn_line', [ 'item.id', '=', 'txn_line.item_id' ])
          ->join('txn', [ 'txn_line.txn_id', '=', 'txn.id' ])
          ->where('txn.type', 'customer')
          ->where_raw('filled BETWEEN ? and ? + INTERVAL 1 DAY', [ $begin, $end ])
          ->group_by('item.id')
          ->find_many()
    ];
  }

  public function kitItems() {
    return [ "items" => $this->data->factory('Item')
                             ->select('*')
                             ->select_expr('(SELECT SUM(allocated)
                                               FROM txn_line
                                              WHERE item.id = txn_line.item_id)',
                                           'stock')
                             ->select_expr('(SELECT COUNT(*)
                                               FROM kit_item
                                               JOIN item i2 ON kit_item.kit_id = i2.id
                                              WHERE i2.active AND item.id = kit_item.item_id)',
                                           'in_kit')
                             ->where_gt('active', 0)
                             ->where_gt('purchase_quantity', 0)
                             ->where_not_equal('is_kit', 1)
                             ->having_gt('in_kit', 0)
                             ->order_by_asc('code')
                             ->find_many() ];
  }

  public function performance($begin= null, $end= null, $items= null) {
    $sql_criteria= "1=1";
    if ($items) {
      list($sql_criteria, $x)= $this->search->buildSearchItemsWhere($items);
    }

    $q= "SELECT SUM(ordered *
                    sale_price(txn_line.retail_price, txn_line.discount_type,
                               txn_line.discount)) total
           FROM txn
           JOIN txn_line ON (txn.id = txn_line.txn_id)
           JOIN item ON (txn_line.item_id = item.id)
           LEFT JOIN product ON item.product_id = product.id
           LEFT JOIN brand ON product.brand_id = brand.id
          WHERE type = 'vendor'
            AND ($sql_criteria)
            AND created BETWEEN ? AND ? + INTERVAL 1 DAY";

    $purchased= $this->data->fetch_single_value($q, [ $begin, $end ]);

    $q= "SELECT SUM(ordered * -1 *
                    sale_price(txn_line.retail_price, txn_line.discount_type,
                               txn_line.discount)) total
           FROM txn
           JOIN txn_line ON (txn.id = txn_line.txn_id)
           JOIN item ON (txn_line.item_id = item.id)
           LEFT JOIN product ON item.product_id = product.id
           LEFT JOIN brand ON product.brand_id = brand.id
          WHERE type = 'customer'
            AND ($sql_criteria)
            AND filled BETWEEN ? AND ? + INTERVAL 1 DAY";

    $sold= $this->data->fetch_single_value($q, [ $begin, $end ]);

    $q= "SELECT SUM((SELECT SUM(allocated) FROM txn_line WHERE item_id = item.id) *
                    sale_price(item.retail_price, item.discount_type,
                               item.discount))
           FROM item
           LEFT JOIN product ON item.product_id = product.id
           LEFT JOIN brand ON product.brand_id = brand.id
          WHERE ($sql_criteria)";

    $stock= $this->data->fetch_single_value($q);

    $q= "SELECT SUM((SELECT SUM(allocated) FROM txn_line WHERE item_id = item.id) *
                    IFNULL((SELECT AVG(retail_price)
                              FROM txn_line
                              JOIN txn ON txn.id = txn_line.txn_id
                             WHERE item_id = item.id
                               AND type = 'vendor'),
                           0))
           FROM item
           LEFT JOIN product ON item.product_id = product.id
           LEFT JOIN brand ON product.brand_id = brand.id
          WHERE ($sql_criteria)";

    $stock_net= $this->data->fetch_single_value($q);

    $q= "SELECT SUM(minimum_quantity *
                    sale_price(item.retail_price, item.discount_type,
                               item.discount))
           FROM item
           LEFT JOIN product ON item.product_id = product.id
           LEFT JOIN brand ON product.brand_id = brand.id
          WHERE ($sql_criteria) AND item.active";

    $ideal= $this->data->fetch_single_value($q);

    $q= "SELECT DATE_FORMAT(created, '%Y-%m') AS x,
               SUM(ordered * -1 *
                    sale_price(txn_line.retail_price, txn_line.discount_type,
                               txn_line.discount)) AS y
           FROM txn
           JOIN txn_line ON (txn.id = txn_line.txn_id)
           JOIN item ON (txn_line.item_id = item.id)
           LEFT JOIN product ON item.product_id = product.id
           LEFT JOIN brand ON product.brand_id = brand.id
          WHERE type = 'customer'
            AND ($sql_criteria)
            AND filled BETWEEN ? AND ? + INTERVAL 1 DAY
          GROUP BY 1
          ORDER BY 1 DESC";

    $sales= $this->data->for_table('txn')->raw_query($q, [ $begin, $end ])->find_many();

    return [
      'begin' => $begin,
      'end' => $end,
      'items' => $items,
      'purchased' => $purchased,
      'sold' => $sold,
      'stock' => $stock,
      'stock_net' => $stock_net,
      'ideal' => $ideal,
      'sales' => $sales,
    ];
  }

  public function priceChanges(\Scat\Service\Catalog $catalog, $vendor, $items_query) {
    $vendors=
      $this->data->factory('Person')
        ->where('role', 'vendor')
        ->where('active', 1)
        ->order_by_expr("IF(company != '' AND company IS NOT NULL, company, name)")
        ->find_many();

    $items= [];

    if ($vendor) {
      $items=
        $catalog
          ->searchItems($items_query . " vendor:" . $vendor)
          ->join('vendor_item', [ 'vendor_item.item_id', '=', 'item.id' ])
          ->where('vendor_item.vendor_id', $vendor)
          ->where('vendor_item.active', 1)
          ->where_raw('ABS(vendor_item.retail_price - item.retail_price) > 0.01')
          ->select_expr('ANY_VALUE(vendor_item.retail_price)', 'new_retail_price')
          ->select_expr('ANY_VALUE(vendor_item.net_price)', 'net_price')
          ->find_many();
    }

    return [
      'vendor' => $vendor,
      'items_query' => $items_query,
      'vendors' => $vendors,
      'items' => $items,
    ];
  }

  public function purchasesByVendor($begin, $end) {
    if (!$begin) {
      $begin= date('Y-m-d', strtotime('30 days ago'));
    }
    if (!$end) {
      $end= date('Y-m-d', strtotime('today'));
    }

    $res= $this->data->factory('Txn')
                ->select('txn.*')
                ->order_by_desc('txn.person_id')
                ->where('type', 'vendor')
                ->left_outer_join('person',
                                  array('person.id', '=', 'txn.person_id'))
                ->where_raw('txn.created BETWEEN ? AND ?', [ $begin, $end ])
                ->find_many();

    $vendors= [];
    $grand_total= 0;

    foreach ($res as $row) {
      if (array_key_exists($row->person_id, $vendors)) {
        $vendor= $vendors[$row->person_id];
      } else {
        $vendor= $row->person();
        if (!$vendor) {
          $vendor= new \stdClass();
        }
        $vendor->orders= 0;
        $vendors[$row->person_id]= $vendor;
      }

      $vendor->orders+= 1;
      $vendor->total+= $row->total();

      $grand_total+= $row->total();
    }

    usort($vendors, function ($a, $b) { return $a->company <=> $b->company; });

    return [
      'vendors' => $vendors,
      'grand_total' => $grand_total,
      'begin' => $begin,
      'end' => $end,
    ];
  }


  public function shipments() {
    return [ "shipments" => $this->data->factory('Shipment')
                             ->select('*')
                             ->where_gt('weight', 0)
                             ->where_raw('created > NOW() - INTERVAL 30 DAY')
                             ->order_by_desc('created')
                             ->find_many() ];
  }

  public function shippingCosts($begin= null, $end= null) {
    if (!$begin) {
      $begin= date('Y-m-d', strtotime('30 days ago'));
    }
    if (!$end) {
      $end= date('Y-m-d', strtotime('today'));
    }

    // by week, we want to know:
    // - the number of shipments
    // - the total spent on shipping
    // - the total collected for shipping
    // - the average order value of orders to be shipped
    // - the average shipping costs
    // we base this on the date of shipments

    $shipments= $this->data->Factory('Shipment')
                  ->select_expr(
                    'DATE_FORMAT(shipment.created, "%X-W%v")', 'week'
                  )
                  ->select_expr('COUNT(*)', 'shipments')
                  ->select_expr('SUM((SELECT SUM(retail_price) FROM txn JOIN txn_line ON txn.id = txn_line.txn_id WHERE txn.id = shipment.txn_id AND item_id IN (31064, 93460)))', 'collected')
                  ->select_expr('SUM(rate)', 'spent')
                  ->select_expr('AVG((SELECT SUM(sale_price(retail_price, discount_type, discount) * -allocated) FROM txn JOIN txn_line ON txn.id = txn_line.txn_id WHERE txn.id = shipment.txn_id AND item_id NOT IN (31064, 93460)))', 'average_order_value')
                  ->where_raw('created BETWEEN ? and ? + INTERVAL 1 DAY',
                              [ $begin, $end ])
                  ->where_raw("(SELECT type FROM txn WHERE txn.id = shipment.txn_id) = 'customer'")
                  ->group_by('week')
                  ->find_many();

    return [
      'shipments' => $shipments,
      'begin' => $begin,
      'end' => $end,
    ];
  }

  public function clock($begin, $end) {
    $punches= $this->data->factory('Timeclock')
                ->select('*')
                ->select_expr('TIMESTAMPDIFF(SECOND, start, end) / 3600', 'hours')
                ->where_raw('start BETWEEN ? and ? + INTERVAL 1 DAY',
                            [ $begin, $end ])
                ->order_by_expr('person_id, start')
                ->find_many();

    $data= $person= [];
    $day= $week= null;
    $day_reg= $day_ot= $week_reg= $week_ot= $total_reg= $total_ot= 0;

    foreach ($punches as $punch) {
      if ($punch->person_id != $person['details']->id) {
        if ($person) {
          $person['regular']= $total_reg;
          $person['overtime']= $total_ot;
          $data['people'][]= $person;
        }

        $day= null;
        $day_reg= $day_ot= $week_reg= $week_ot= $total_reg= $total_ot= 0;

        $person= ['details' => $punch->person() ];
      }

      $start= strtotime($punch->start);
      $punch_day= date("Y-m-d", $start);
      // Need to adjust so week starts on Sunday, not Monday
      $punch_week= date("W", $start) + (date("w", $start) ? 0 : 1);

      // reset day and week as necessary
      if ($day != $punch_day) {
        $day= $punch_day;
        $day_reg= $day_ot= 0;
      }
      if ($week != $punch_week) {
        $week= $punch_week;
        $week_reg= $week_ot= 0;
      }


      $ot= 0;
      $reg= $punch->hours;

      /* Anything over 8 hours in a day shifts to overtime */
      $day_reg+= $reg;
      if ($day_reg > 8.0) {
        $ot= $day_reg - 8.0;
        $reg= $reg - $ot;
        $day_ot+= $ot;
        $day_reg= 8.0;
      }

      /* Any regular time over 40 hours in a week also becomes overtime */
      $week_reg+= $reg;
      if ($week_reg > 40.0) {
        $new_ot= $week_reg - 40.0;
        $reg= $reg - $new_ot;
        $ot+= $new_ot;
        $week_ot+= $new_ot;
        $week_reg= 40.0;
      }

      $punch->regular= $reg;
      $punch->overtime= $ot;

      $person['punches'][]= $punch;

      $total_reg+= $reg;
      $total_ot+= $ot;
    }

    if ($person) {
      $person['regular']= $total_reg;
      $person['overtime']= $total_ot;
      $data['people'][]= $person;
    }

    return $data;
  }

  public function summary($date) {
    $q= "SELECT SUM(taxed + untaxed) AS sales,
                SUM(IF(uuid,
                       tax,
                       ROUND_TO_EVEN(taxed * (tax_rate / 100), 2))) AS tax,
                SUM(IF(uuid,
                       untaxed + taxed + tax,
                       ROUND_TO_EVEN(taxed * (1 + (tax_rate / 100)), 2) + untaxed))
                  AS total
           FROM (SELECT
                        filled,
                        txn.uuid,
                        CAST(ROUND_TO_EVEN(
                          SUM(IF(txn_line.taxfree, 1, 0) *
                            IF(type = 'customer', -1, 1) * ordered *
                            sale_price(txn_line.retail_price,
                                       txn_line.discount_type,
                                       txn_line.discount)),
                          2) AS DECIMAL(9,2))
                        AS untaxed,
                        CAST(ROUND_TO_EVEN(
                          SUM(IF(txn_line.taxfree, 0, 1) *
                            IF(type = 'customer', -1, 1) * ordered *
                            sale_price(txn_line.retail_price,
                                       txn_line.discount_type,
                                       txn_line.discount)),
                          2) AS DECIMAL(9,2))
                        AS taxed,
                        SUM(tax) AS tax,
                        tax_rate
                   FROM txn
                   LEFT JOIN txn_line ON (txn.id = txn_line.txn_id)
                        JOIN item ON (txn_line.item_id = item.id)
                  WHERE filled IS NOT NULL
                    AND filled BETWEEN ? AND ? + INTERVAL 1 DAY
                    AND type = 'customer'
                    AND code NOT LIKE 'ZZ-gift%'
                  GROUP BY txn.id
                ) t";

    $sales= $this->data->fetch_single_row($q, [ $date, $date ]);

    $q= "SELECT COUNT(*) total,
                IFNULL(SUM(IF(DATE(filled) != DATE(person.created), 1, 0)), 0) returned,
                IFNULL(SUM(IF(DATE(filled) = DATE(person.created), 1, 0)), 0) new
           FROM txn LEFT JOIN person ON (txn.person_id = person.id)
          WHERE DATE(filled) = ?";
    $people= $this->data->fetch_single_row($q, [ $date ]);

    $hourly= $this->getSummarySalesData('%Y-%m-%d %H:00', $date);

    $begin= (new \Datetime($date))->sub(new \DateInterval('P8D'))->format('Y-m-d');

    $daily= $this->getSummarySalesData('%Y-%m-%d', $begin, $date);

    $q= "SELECT AVG(sales) FROM (SELECT DATE(filled),
                SUM(taxed + untaxed) AS sales
           FROM (SELECT
                        filled,
                        CAST(ROUND_TO_EVEN(
                          SUM(IF(txn_line.taxfree, 1, 0) *
                            IF(type = 'customer', -1, 1) * ordered *
                            sale_price(txn_line.retail_price,
                                       txn_line.discount_type,
                                       txn_line.discount)),
                          2) AS DECIMAL(9,2))
                        AS untaxed,
                        CAST(ROUND_TO_EVEN(
                          SUM(IF(txn_line.taxfree, 0, 1) *
                            IF(type = 'customer', -1, 1) * ordered *
                            sale_price(txn_line.retail_price,
                                       txn_line.discount_type,
                                       txn_line.discount)),
                          2) AS DECIMAL(9,2))
                        AS taxed,
                        tax_rate
                   FROM txn
                   LEFT JOIN txn_line ON (txn.id = txn_line.txn_id)
                        JOIN item ON (txn_line.item_id = item.id)
                  WHERE filled IS NOT NULL
                    AND filled BETWEEN ? - INTERVAL 3 MONTH AND ? + INTERVAL 1 DAY
                    AND DAYOFWEEK(filled) = DAYOFWEEK(?)
                    AND type = 'customer'
                    AND code NOT LIKE 'ZZ-gift%'
                  GROUP BY txn.id
                ) t
            GROUP BY 1) s";
    $same_day= $this->data->fetch_single_value($q, [ $date, $date, $date ]);

    $q= "SELECT AVG(sales) FROM (SELECT DATE(filled),
                SUM(taxed + untaxed) AS sales
           FROM (SELECT
                        filled,
                        CAST(ROUND_TO_EVEN(
                          SUM(IF(txn_line.taxfree, 1, 0) *
                            IF(type = 'customer', -1, 1) * ordered *
                            sale_price(txn_line.retail_price,
                                       txn_line.discount_type,
                                       txn_line.discount)),
                          2) AS DECIMAL(9,2))
                        AS untaxed,
                        CAST(ROUND_TO_EVEN(
                          SUM(IF(txn_line.taxfree, 0, 1) *
                            IF(type = 'customer', -1, 1) * ordered *
                            sale_price(txn_line.retail_price,
                                       txn_line.discount_type,
                                       txn_line.discount)),
                          2) AS DECIMAL(9,2))
                        AS taxed,
                        tax_rate
                   FROM txn
                   LEFT JOIN txn_line ON (txn.id = txn_line.txn_id)
                        JOIN item ON (txn_line.item_id = item.id)
                  WHERE filled IS NOT NULL
                    AND filled BETWEEN ? - INTERVAL 7 DAY AND ? + INTERVAL 1 DAY
                    AND type = 'customer'
                    AND code NOT LIKE 'ZZ-gift%'
                  GROUP BY txn.id
                ) t
            GROUP BY 1) s";

    $last_week= $this->data->fetch_single_value($q, [ $date, $date ]);

    $items= $this->search->buildSearchItems("");

    return [
      'date' => $date,
      'sales' => $sales,
      'people' => $people,
      'hourly' => $hourly,
      'daily' => $daily,
      'same_day' => $same_day,
      'last_week' => $last_week,
      'items' =>
        $items
          ->select_expr('SUM(-1 * allocated)', 'sold')
          ->select_expr('AVG(sale_price(txn_line.retail_price, txn_line.discount_type,
                                        txn_line.discount))',
                        'average')
          ->select_expr('SUM(-1 * allocated * sale_price(txn_line.retail_price,
                                                          txn_line.discount_type,
                                                          txn_line.discount))',
                        'total')
          ->join('txn_line', [ 'item.id', '=', 'txn_line.item_id' ])
          ->join('txn', [ 'txn_line.txn_id', '=', 'txn.id' ])
          ->where('txn.type', 'customer')
          ->where_raw('filled BETWEEN ? and ? + INTERVAL 1 DAY', [ $date, $date ])
          ->group_by('item.id')
          ->order_by_desc('total') // TODO doesn't work because of pre-existing order_by
          ->limit(12)
          ->find_many()
    ];
  }

  protected function getSummarySalesData($format, $begin, $end= null) {
    if (!$end) {
      $end= (new \Datetime($begin))->add(new \DateInterval('P1D'))->format('Y-m-d');
    }

    $q= "SELECT DATE_FORMAT(filled, ?) AS span,
                COUNT(*) AS txns,
                SUM(taxed + untaxed) AS total,
                SUM(IF(tax_rate, 0, taxed + untaxed)) AS resale,
                SUM(ROUND_TO_EVEN(taxed * (tax_rate / 100), 2)) AS tax,
                SUM(ROUND_TO_EVEN(taxed * (1 + (tax_rate / 100)), 2) + untaxed)
                  AS total_taxed,
                MIN(DATE(filled)) AS raw_date
           FROM (SELECT
                        filled,
                        CAST(ROUND_TO_EVEN(
                          SUM(IF(txn_line.taxfree, 1, 0) *
                            IF(type = 'customer', -1, 1) * ordered *
                            sale_price(txn_line.retail_price,
                                       txn_line.discount_type,
                                       txn_line.discount)),
                          2) AS DECIMAL(9,2))
                        AS untaxed,
                        CAST(ROUND_TO_EVEN(
                          SUM(IF(txn_line.taxfree, 0, 1) *
                            IF(type = 'customer', -1, 1) * ordered *
                            sale_price(txn_line.retail_price,
                                       txn_line.discount_type,
                                       txn_line.discount)),
                          2) AS DECIMAL(9,2))
                        AS taxed,
                        tax_rate
                   FROM txn
                   LEFT JOIN txn_line ON (txn.id = txn_line.txn_id)
                        JOIN item ON (txn_line.item_id = item.id)
                  WHERE filled IS NOT NULL
                    AND filled BETWEEN ? AND ?
                    AND type = 'customer'
                    AND code NOT LIKE 'ZZ-gift%'
                  GROUP BY txn.id
                ) t
          GROUP BY 1
          ORDER BY 1 DESC";

    $rows= $this->data->for_table('txn')->raw_query($q, [ $format, $begin, $end ])->find_many();

    $sales= $txns= [];

    foreach ($rows as $row) {
      $sales[]= [
        'x' => $row['span'],
        'y' => (float)$row['total']
      ];
      $txns[]= [
        'x' => $row['span'],
        'y' => (int)$row['txns']
      ];
    }

    return [
      'datasets' => [
        [
          'label' => 'Sales',
          'yAxisID' => 'sales',
          'backgroundColor' => 'rgba(0,0,0,0.3)',
          'borderColor' => 'rgba(0,0,0,0.3)',
          'data' => $sales,
        ],
        [
          'type' => 'line',
          'fill' => false,
          'label' => 'Transactions',
          'yAxisID' => 'txns',
          'backgroundColor' => 'rgba(99,0,0,0.3)',
          'borderColor' => 'rgba(99,0,0,0.3)',
          'data' => $txns,
        ]
      ]
    ];
  }
}
