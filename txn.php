<?
require 'scat.php';

head("transaction");

$type= $_REQUEST['type'];
$number= (int)$_REQUEST['number'];

if (!$type || !$number) die("no transaction specified.");

$type= $db->real_escape_string($type);

$q= "SELECT
            item.code Code\$item,
            item.name Name,
            retail_price MSRP\$dollar,
            IF(discount_type,
               CASE discount_type
                 WHEN 'percentage' THEN ROUND(retail_price * ((100 - discount) / 100), 2)
                 WHEN 'relative' THEN (retail_price - discount) 
                 WHEN 'fixed' THEN (discount)
               END,
               NULL) Sale\$dollar,
            CASE discount_type
              WHEN 'percentage' THEN CONCAT(ROUND(discount), '% off')
              WHEN 'relative' THEN CONCAT('$', discount, ' off')
            END Discount,
            ordered as Ordered,
            allocated as Allocated
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
       JOIN item ON (txn_line.item = item.id)
      WHERE number = '$number' AND type = '$type'
      ORDER BY line ASC";

dump_table($db->query($q));
dump_query($q);
