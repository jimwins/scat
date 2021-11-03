<?
include '../scat.php';
include '../lib/item.php';

$begin= $_REQUEST['begin'];
$end= $_REQUEST['end'];

$items= "1=1";
if ($_REQUEST['items']) {
  list($items, $x)= item_terms_to_sql($db, $_REQUEST['items'],
                                      FIND_OR|FIND_ALL|FIND_LIMITED);
}

if (!$begin) {
  $days= $_REQUEST['days'];
  if (!$days) $days= 10;
  $begin= "DATE(NOW() - INTERVAL 10 DAY)";
} else {
  $begin= "'" . $db->escape($begin) . "'";
}

if (!$end) {
  $end= "DATE(NOW() + INTERVAL 1 DAY)";
} else {
  $end= "'" . $db->escape($end) . "' + INTERVAL 1 DAY";
}

$span= $_REQUEST['span'];
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
  $format= '%x-W%v';
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
                    JOIN brand ON (item.brand_id = brand.id)
              WHERE
                    created BETWEEN $begin AND $end
                AND type = 'vendor'
                AND ($items)
              GROUP BY txn.id
            ) t
      GROUP BY 1
      ORDER BY 1 DESC";

$r= $db->query($q)
  or die_query($db, $q);

$sales= array();
while ($row= $r->fetch_assoc()) {
  $row['total']= (float)$row['total'];
  $sales[]= $row;
}

echo jsonp(array("days" => $days, "sales" => $sales));
