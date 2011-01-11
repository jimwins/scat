<?php
require 'scat.php';

head("convert");

# load basics
$q= "INSERT INTO item (id, code, name, minimum_quantity, active, deleted)
     SELECT id,
            (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 15 ORDER BY id DESC LIMIT 1) code,
            (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 2 ORDER BY id DESC LIMIT 1) name,
            (SELECT minimum FROM co.stock WHERE id_product = item.id AND stocktype = 1) min,
            (active = 't') active,
            (deleted = 't') deleted
       FROM co.item
      WHERE deleted = 'f' AND type = 2
     ON DUPLICATE KEY
     UPDATE code = VALUES(code),
            name = VALUES(name),
            minimum_quantity = VALUES(minimum_quantity),
            active = VALUES(active),
            deleted = VALUES(deleted)";
$r= $db->query($q) or die("query failed: ". $db->error);
echo "Loaded ", $db->affected_rows, " items.<br>";

# load brands
$q= "INSERT IGNORE INTO brand (name)
     SELECT (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 14 ORDER BY id DESC LIMIT 1)
       FROM item";
$r= $db->query($q) or die("query failed: ". $db->error);
echo "Loaded ", $db->affected_rows, " brands.<br>";

$q= "UPDATE item SET
       brand = (SELECT id FROM brand WHERE brand.name = (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 14 ORDER BY id DESC LIMIT 1))";
$r= $db->query($q) or die("query failed: ". $db->error);
echo "Set brand on ", $db->affected_rows, " rows.<br>";

# load pricing
$q= "CREATE TEMPORARY TABLE co_pricing
     SELECT id,
            (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 16 ORDER BY id DESC LIMIT 1) description,
            (SELECT value FROM co.metanumber WHERE id_item = item.id AND id_metatype = 17 ORDER BY id DESC LIMIT 1) price
      FROM item";
$r= $db->query($q) or die("query failed: ". $db->error);
$q= "UPDATE item, co_pricing
        SET retail_price = IF(description IS NULL OR description NOT LIKE 'MSRP%',
                              price,
                              SUBSTRING_INDEX(description, 'MSRP $', -1)),
            discount_type = IF(description LIKE '%Sale: %',
                               'percentage',
                               NULL),
            discount= IF(description LIKE '%Sale: %',
                         SUBSTRING_INDEX(description,'/ Sale: ',-1),
                         NULL)
      WHERE item.id = co_pricing.id";
$r= $db->query($q) or die("query failed: ". $db->error);
echo "Updated pricing on ", $db->affected_rows, " rows.<br>";

# load barcodes
$q= "INSERT IGNORE INTO barcode (code, item)
     SELECT (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 13 ORDER BY id DESC LIMIT 1) AS code,
            id AS item
       FROM item";
$r= $db->query($q) or die("query failed: ". $db->error);
echo "Loaded ", $db->affected_rows, " barcodes.<br>";

/*
SELECT
       id,
       (SELECT value FROM metavalue WHERE id_item = item.id AND id_metatype = 15 ORDER BY id DESC LIMIT 1) code,
       (SELECT value FROM metavalue WHERE id_item = item.id AND id_metatype = 2 ORDER BY id DESC LIMIT 1) name,
       (SELECT value FROM metavalue WHERE id_item = item.id AND id_metatype = 14 ORDER BY id DESC LIMIT 1) brand,
       (SELECT value FROM metavalue WHERE id_item = item.id AND id_metatype = 16 ORDER BY id DESC LIMIT 1) description,
       (SELECT value FROM metavalue WHERE id_item = item.id AND id_metatype = 13 ORDER BY id DESC LIMIT 1) barcode,
       (SELECT value FROM metanumber WHERE id_item = item.id AND id_metatype = 17 ORDER BY id DESC LIMIT 1) sale,
       (SELECT value FROM metanumber WHERE id_item = item.id AND id_metatype = 18 ORDER BY id DESC LIMIT 1) net,
       has_serial,
       has_stock,
       (SELECT minimum FROM stock WHERE id_product = item.id AND stocktype = 1) min,
       active,
       deleted
  FROM item
 WHERE deleted != 't' AND type = 2;
 */
