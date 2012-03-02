<?
include '../scat.php';

$begin= $_REQUEST['begin'];
$end= $_REQUEST['end'];

if (!$begin) {
  $days= $_REQUEST['days'];
  if (!$days) $days= 10;
  $begin= "NOW() - INTERVAL 10 DAY";
} else {
  $begin= "'" . $db->escape($begin) . "'";
}

if (!$end) {
  $end= "NOW()";
} else {
  $end= "'" . $db->escape($end) . "'";
}

$span= $_REQUEST['span'];
switch ($span) {
case 'month':
  $format= '%Y-%m';
  break;
case 'week':
  $format= '%X-W%v';
  break;
case 'day':
default:
  $format= '%Y-%m-%d';
  break;
}

$q= "SELECT DATE_FORMAT(filled, '$format') AS span,
            SUM(subtotal) AS total
       FROM (SELECT 
                    filled,
                    CAST(ROUND_TO_EVEN(
                      SUM(IF(type = 'customer', -1, 1) * allocated *
                              CASE txn_line.discount_type
                                WHEN 'percentage'
                                  THEN txn_line.retail_price *
                                       ((100 - txn_line.discount) / 100)
                                WHEN 'relative'
                                  THEN (txn_line.retail_price -
                                        txn_line.discount) 
                                WHEN 'fixed'
                                  THEN (txn_line.discount)
                                ELSE txn_line.retail_price
                              END), 2) AS DECIMAL(9,2))
                    AS subtotal
               FROM txn
               LEFT JOIN txn_line ON (txn.id = txn_line.txn)
                    JOIN item ON (txn_line.item = item.id)
              WHERE filled IS NOT NULL
                AND filled BETWEEN $begin AND $end
                AND type = 'customer'
                AND code NOT LIKE 'ZZ-gift%'
              GROUP BY txn.id
            ) t
      GROUP BY 1 DESC";

$r= $db->query($q)
  or die_query($db, $q);

$sales= array();
while ($row= $r->fetch_assoc()) {
  $row['total']= (float)$row['total'];
  $sales[]= $row;
}

echo jsonp(array("days" => $days, "sales" => $sales));
