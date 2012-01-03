<?
include '../scat.php';

$days= (int)$_REQUEST['days'];
if (!$days) $days= 30;

$q= "SELECT DATE_FORMAT(filled, '%Y-%m-%d') day,
            SUM(subtotal) AS total
       FROM (SELECT 
                    filled,
                    CAST(ROUND_TO_EVEN(
                      SUM(IF(type = 'customer', -1, 1) * allocated *
                              CASE discount_type
                                WHEN 'percentage'
                                  THEN retail_price * ((100 - discount) / 100)
                                WHEN 'relative'
                                  THEN (retail_price - discount) 
                                WHEN 'fixed'
                                  THEN (discount)
                                ELSE retail_price
                              END), 2) AS DECIMAL(9,2))
                    AS subtotal
               FROM txn
               LEFT JOIN txn_line ON (txn.id = txn_line.txn)
              WHERE filled IS NOT NULL
                AND filled > DATE(NOW()) - INTERVAL $days DAY
                AND type = 'customer'
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

echo generate_jsonp(array("days" => $days, "sales" => $sales));
