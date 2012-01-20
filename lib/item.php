<?

function item_load($db, $id) {
  $q= "SELECT
              item.id, item.code, item.name, brand.name brand,
              retail_price retail_price,
              IF(discount_type,
                 CASE discount_type
                   WHEN 'percentage' THEN ROUND(retail_price * ((100 - discount) / 100), 2)
                   WHEN 'relative' THEN (retail_price - discount) 
                   WHEN 'fixed' THEN (discount)
                 END,
                 NULL) sale_price,
              discount_type, discount,
              CASE discount_type
                WHEN 'percentage' THEN CONCAT(ROUND(discount), '% off')
                WHEN 'relative' THEN CONCAT('$', discount, ' off')
              END discount_label,
              (SELECT SUM(allocated) FROM txn_line WHERE item = item.id) stock,
              (SELECT retail_price
                 FROM txn_line JOIN txn ON (txn_line.txn = txn.id)
                WHERE txn_line.item = item.id AND txn.type = 'vendor'
                  AND filled IS NOT NULL
                ORDER BY filled DESC
                LIMIT 1) last_net,
              minimum_quantity min,
              GROUP_CONCAT(barcode.code SEPARATOR '|') barcodes
         FROM item
         JOIN brand ON (item.brand = brand.id)
    LEFT JOIN barcode ON (item.id = barcode.item)
        WHERE item.id = $id
     GROUP BY item.id";

  $r= $db->query($q)
    or die_query($db, $q);

  return $r->fetch_assoc();
}
