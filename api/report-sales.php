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

$q= "SELECT DATE_FORMAT(filled, '$format') AS span,
            SUM(taxed + untaxed) AS total,
            SUM(IF(tax_rate, 0, taxed + untaxed)) AS resale,
            SUM(ROUND_TO_EVEN(taxed * (tax_rate / 100), 2)) AS tax,
            SUM(ROUND_TO_EVEN(taxed * (1 + (tax_rate / 100)), 2) + untaxed)
              AS total_taxed
       FROM (SELECT 
                    filled,
                    CAST(ROUND_TO_EVEN(
                      SUM(IF(txn_line.taxfree, 1, 0) *
                        IF(type = 'customer', -1, 1) * ordered *
                        CASE txn_line.discount_type
                          WHEN 'percentage' THEN txn_line.retail_price * ((100 - txn_line.discount) / 100)
                          WHEN 'relative' THEN (txn_line.retail_price - txn_line.discount) 
                          WHEN 'fixed' THEN (txn_line.discount)
                          ELSE txn_line.retail_price
                        END),
                      2) AS DECIMAL(9,2))
                    AS untaxed,
                    CAST(ROUND_TO_EVEN(
                      SUM(IF(txn_line.taxfree, 0, 1) *
                        IF(type = 'customer', -1, 1) * ordered *
                        CASE txn_line.discount_type
                          WHEN 'percentage' THEN txn_line.retail_price * ((100 - txn_line.discount) / 100)
                          WHEN 'relative' THEN (txn_line.retail_price - txn_line.discount) 
                          WHEN 'fixed' THEN (txn_line.discount)
                          ELSE txn_line.retail_price
                        END),
                      2) AS DECIMAL(9,2))
                    AS taxed,
                    tax_rate
               FROM txn
               LEFT JOIN txn_line ON (txn.id = txn_line.txn)
                    JOIN item ON (txn_line.item = item.id)
              WHERE filled IS NOT NULL
                AND filled BETWEEN $begin AND $end
                AND type = 'customer'
                AND code NOT LIKE 'ZZ-gift%'
                AND ($items)
              GROUP BY txn.id
            ) t
      GROUP BY 1 DESC";

$r= $db->query($q)
  or die_query($db, $q);

$sales= array();
while ($row= $r->fetch_assoc()) {
  $row['total']= (float)$row['total'];
  $row['resale']= (float)$row['resale'];
  $row['tax']= (float)$row['tax'];
  $row['total_taxed']= (float)$row['total_taxed'];
  $sales[]= $row;
}

echo jsonp(array("days" => $days, "sales" => $sales));
