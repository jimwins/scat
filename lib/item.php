<?

define('FIND_ALL', 1);
define('FIND_OR', 2);
define('FIND_SALES', 4);

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
    } elseif (preg_match('/^begin:([-0-9]+)/i', $term, $dbt)) {
      $begin= $dbt[1];
    } elseif (preg_match('/^-(.+)/i', $term, $dbt)) {
      $not[]= "(item.code NOT LIKE '{$dbt[1]}%')";
    } else {
      $andor[]= "(item.name LIKE '%$term%'
               OR brand.name LIKE '%$term%'
               OR item.code LIKE '%$term%'
               OR barcode.code LIKE '%$term%')";
    }
  }

  $sql_criteria= join(($options & FIND_OR) ? ' OR ' : ' AND ', $andor);
  if (count($not)) {
    $sql_criteria= "($sql_criteria) AND " . join(' AND ', $not);
  }

  if (!($options & FIND_ALL))
    $sql_criteria= "($sql_criteria) AND (active AND NOT deleted)";

  return array($sql_criteria, $begin);
}

function generate_upc($code) {
  assert(strlen($code) == 11);
  $check= 0;
  foreach (range(0,10) as $digit) {
    $check+= $code[$digit] * (($digit % 2) ? 1 : 3);
  }

  $cd= 10 - ($check % 10);
  if ($cd == 10) $cd= 0;

  return $code.$cd;
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
              item.id, item.code, item.name,
              brand.id brand_id, brand.name brand,
              retail_price retail_price,
              IF(item.discount_type,
                 CASE item.discount_type
                   WHEN 'percentage' THEN ROUND(retail_price * ((100 - item.discount) / 100), 2)
                   WHEN 'relative' THEN (retail_price - item.discount) 
                   WHEN 'fixed' THEN (item.discount)
                 END,
                 NULL) sale_price,
              item.discount_type, item.discount,
              CASE item.discount_type
                WHEN 'percentage' THEN CONCAT(ROUND(item.discount), '% off')
                WHEN 'relative' THEN CONCAT('$', item.discount, ' off')
                ELSE ''
              END discount_label,
              (SELECT SUM(allocated) FROM txn_line WHERE item = item.id) stock,
              (SELECT retail_price
                 FROM txn_line JOIN txn ON (txn_line.txn = txn.id)
                WHERE txn_line.item = item.id AND txn.type = 'vendor'
                  AND filled IS NOT NULL
                ORDER BY filled DESC
                LIMIT 1) last_net,
              minimum_quantity,
              GROUP_CONCAT(CONCAT(barcode.code, '!', barcode.quantity)
                           SEPARATOR ',') barcodes,
              $extra
              active
         FROM item
    LEFT JOIN brand ON (item.brand = brand.id)
    LEFT JOIN barcode ON (item.id = barcode.item)
        WHERE $sql_criteria
     GROUP BY item.id
     ORDER BY 2";

  $r= $db->query($q)
    or die($db->error);

  $items= array();
  while ($item= $r->fetch_assoc()) {
    $item['stock']= (int)$item['stock'];
    $item['minimum_quantity']= (int)$item['minimum_quantity'];

    $barcodes= explode(',', $item['barcodes']);
    $item['barcode']= array();
    foreach ($barcodes as $barcode) {
      list($code, $quantity)= explode('!', $barcode);
      $item['barcode'][$code]= $quantity;
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
