<?
include '../scat.php';

$q= $_GET['q'];
if (!$q) exit;

$terms= preg_split('/\s+/', $q);
$criteria= array();
foreach ($terms as $term) {
  $term= $db->real_escape_string($term);
  if (preg_match('/^code:(.+)/i', $term, $dbt)) {
    $criteria[]= "(item.code LIKE '{$dbt[1]}%')";
  } else {
    $criteria[]= "(item.name LIKE '%$term%'
               OR brand.name LIKE '%$term%'
               OR item.code LIKE '%$term%'
               OR barcode.code LIKE '%$term%')";
  }
}
# allow option to include inactive and/or deleted
if (!$_REQUEST['all']) {
  $criteria[]= "(active AND NOT deleted)";
} else {
  $criteria[]= "(NOT deleted)";
}

$q= "SELECT
            item.code code,
            item.name name,
            brand.name brand,
            retail_price msrp,
            CASE discount_type
              WHEN 'percentage' THEN ROUND(retail_price * ((100 - discount) / 100), 2)
              WHEN 'relative' THEN (retail_price - discount) 
              WHEN 'fixed' THEN (discount)
              ELSE retail_price
            END price,
            CASE discount_type
              WHEN 'percentage' THEN CONCAT(ROUND(discount), '% off')
              WHEN 'relative' THEN CONCAT('$', discount, ' off')
            END discount,
            (SELECT SUM(allocated) FROM txn_line WHERE item = item.id) stock
       FROM item
  LEFT JOIN brand ON (item.brand = brand.id)
  LEFT JOIN barcode ON (item.id = barcode.item)
      WHERE " . join(' AND ', $criteria) . "
   GROUP BY item.id
      LIMIT 10";

$r= $db->query($q);
if (!$r) {
  die(json_encode(array('error' => 'Query failed. ' . $db->error,
                        'query' => $q)));
}

$items= array();
while ($row= $r->fetch_assoc()) {
  $items[]= $row;
}

echo json_encode($items);
