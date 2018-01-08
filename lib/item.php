<?

define('FIND_ALL', 1);
define('FIND_OR', 2);
define('FIND_SALES', 4);
define('FIND_LIMITED', 8);

function item_terms_to_sql($db, $q, $options) {
  $andor= array();
  $not= array();
  $begin= false;

  $terms= preg_split('/\s+/', $q);
  foreach ($terms as $term) {
    $term= $db->real_escape_string($term);
    if (preg_match('/^code:(.+)/i', $term, $dbt)) {
      $andor[]= "(item.code LIKE '{$dbt[1]}%')";
    } elseif (preg_match('/^400400(\d+)\d$/i', $term, $dbt)) {
      $andor[]= "(item.id = '{$dbt[1]}')";
    } elseif (preg_match('/^item:(.+)/i', $term, $dbt)) {
      $andor[]= "(item.id = '{$dbt[1]}')";
    } elseif (preg_match('/^brand:(.+)/i', $term, $dbt)) {
      $andor[]= "(brand.slug = '{$dbt[1]}')";
    } elseif (preg_match('/^begin:([-0-9]+)/i', $term, $dbt)) {
      $begin= $dbt[1];
    } elseif (preg_match('/^-(.+)/i', $term, $dbt)) {
      $not[]= "(item.code NOT LIKE '{$dbt[1]}%')";
    } elseif (preg_match('/^name:(.+)/i', $term, $dbt)) {
      $andor[]= "(item.name LIKE '%{$dbt[1]}%')";
    } elseif (preg_match('/^msrp:(.+)/i', $term, $dbt)) {
      $andor[]= "(item.retail_price = '{$dbt[1]}')";
    } elseif (preg_match('/^min:(.+)/i', $term, $dbt)) {
      $andor[]= "(item.minimum_quantity = '{$dbt[1]}')";
    } elseif (preg_match('/^stocked:(.+)/i', $term, $dbt)) {
      $andor[]= $dbt[1] ? "(item.minimum_quantity)"
                        : "(NOT item.minimum_quantity)";
    } elseif (preg_match('/^reviewed:(.+)/i', $term, $dbt)) {
      $andor[]= $dbt[1] ? "(item.reviewed)"
                        : "(NOT item.reviewed)";
    } elseif (preg_match('/^vendor:(.+)/i', $term, $dbt)) {
      $vendor= (int)$dbt[1];
      $andor[]= $vendor ? "EXISTS (SELECT id
                                     FROM vendor_item
                                    WHERE item = item.id
                                      AND vendor = $vendor
                                      AND vendor_item.active)"
                        : "NOT EXISTS (SELECT id
                                         FROM vendor_item
                                        WHERE item = item.id
                                          AND vendor_item.active)";
    } else {
      if ($options & FIND_LIMITED) {
        $andor[]= "(item.name LIKE '%$term%'
                 OR item.code LIKE '%$term%')";
      } else {
        $andor[]= "(item.name LIKE '%$term%'
                 OR brand.name LIKE '%$term%'
                 OR item.code LIKE '%$term%'
                 OR barcode.code LIKE '%$term%')";
      }
    }
  }

  $sql_criteria= join(($options & FIND_OR) ? ' OR ' : ' AND ', $andor);
  if (count($not)) {
    $sql_criteria= "($sql_criteria) AND " . join(' AND ', $not);
  }

  if (!($options & FIND_ALL))
    $sql_criteria= "($sql_criteria) AND (item.active AND NOT item.deleted)";

  return array($sql_criteria, $begin);
}

function item_find($db, $q, $options) {
  list($sql_criteria, $begin) = item_terms_to_sql($db, $q, $options);

  $extra= "";
  if (!$begin) {
    $begin= date("Y-m-d", time() - 7*24*3600);
  }
  if ($options & FIND_SALES) {
    $extra= "(SELECT SUM(allocated) * -1
                FROM txn_line JOIN txn ON txn.id = txn_line.txn
               WHERE txn_line.item = item.id
                 AND type = 'customer'
                 AND filled >= '$begin') sold,";
  }

  $q= "SELECT
              item.id, item.product_id, item.code, item.name,
              item.short_name, item.variation,
              brand.id brand_id, brand.name brand,
              retail_price retail_price,
              sale_price(retail_price, item.discount_type, item.discount)
                AS sale_price,
              item.discount_type, item.discount,
              CASE item.discount_type
                WHEN 'percentage' THEN CONCAT(ROUND(item.discount), '% off')
                WHEN 'relative' THEN CONCAT('$', item.discount, ' off')
                ELSE ''
              END discount_label,
              (SELECT IFNULL(SUM(allocated),0) FROM txn_line
                WHERE item = item.id) stock,
              (SELECT retail_price
                 FROM txn_line JOIN txn ON (txn_line.txn = txn.id)
                WHERE txn_line.item = item.id AND txn.type = 'vendor'
                  AND filled IS NOT NULL
                ORDER BY filled DESC
                LIMIT 1) last_net,
              minimum_quantity, purchase_quantity,
              GROUP_CONCAT(CONCAT(barcode.code, '!', barcode.quantity)
                           SEPARATOR ',') barcodes,
              length, width, height, color,
              item.tic,
              $extra
              item.active, item.reviewed
         FROM item
    LEFT JOIN brand ON (item.brand = brand.id)
    LEFT JOIN barcode ON (item.id = barcode.item)
        WHERE $sql_criteria
     GROUP BY item.id
     ORDER BY !(stock > 0), 2";

  $r= $db->query($q)
    or die($db->error);

  $items= array();
  while ($item= $r->fetch_assoc()) {
    $item['active']= (int)$item['active'];
    $item['reviewed']= (int)$item['reviewed'];
    $item['stock']= (int)$item['stock'];
    $item['minimum_quantity']= (int)$item['minimum_quantity'];
    $item['purchase_quantity']= (int)$item['purchase_quantity'];

    $barcodes= explode(',', $item['barcodes']);
    $item['barcode']= $item['barcode_list']= array();
    foreach ($barcodes as $barcode) {
      list($code, $quantity)= explode('!', $barcode);
      if (!strlen($code)) continue;
      $item['barcode'][$code]= $quantity;
      $item['barcode_list'][]= array('code' => $code, 'quantity' => $quantity);
    }

    $item['fake_barcode']= generate_upc(sprintf("4004%07d", $item['id']));

    $items[]= $item;
  }

  return $items;
}

function item_load($db, $id) {
  $items= item_find($db, "item:$id", FIND_ALL);
  return $items[0];
}
