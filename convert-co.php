<?php
require 'scat.php';

head("convert");

# ITEMS
#
# load item basics
$q= "INSERT INTO item (id, code, name, minimum_quantity, active, deleted)
     SELECT id,
            (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 15 ORDER BY id DESC LIMIT 1) code,
            (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 2 ORDER BY id DESC LIMIT 1) name,
            (SELECT minimum FROM co.stock WHERE id_product = item.id AND stocktype = 1) min,
            (active = 't') active,
            (deleted = 't') deleted
       FROM co.item
      WHERE type = 2
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
            discount_type = IF(description LIKE '%/ Sale: %',
                               'percentage',
                               NULL),
            discount= IF(description LIKE '%/ Sale: %',
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

# PERSONS
#
$q= "INSERT INTO person (id, name, company, address, email, phone, tax_id,
                         active, deleted)
     SELECT id,
            (SELECT REPLACE(REPLACE(value, '|', ' '), '  ', ' ') FROM co.metavalue WHERE id_item = item.id AND id_metatype = 2 ORDER BY id DESC LIMIT 1) name,
            (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 3 ORDER BY id DESC LIMIT 1) company,
            (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 5 ORDER BY id DESC LIMIT 1) address,
            (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 9 ORDER BY id DESC LIMIT 1) email,
            (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 8 ORDER BY id DESC LIMIT 1) phone,
            (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 6 ORDER BY id DESC LIMIT 1) tax_id,
            (active = 't') active,
            (deleted = 't') deleted
       FROM co.item
      WHERE type IN (1,3,6)
     ON DUPLICATE KEY
     UPDATE
            name = VALUES(name),
            company = VALUES(company),
            address = VALUES(address),
            email = VALUES(email),
            phone = VALUES(phone),
            active = VALUES(active),
            deleted = VALUES(deleted)";
$r= $db->query($q) or die("query failed: ". $db->error);
echo "Loaded ", $db->affected_rows, " people.<br>";

# TRANSACTIONS
#
$q= "TRUNCATE txn_line";
$r= $db->query($q) or die("query failed: ". $db->error);
echo "Flushed transaction lines.<br>";

# incomplete transactions
$q= "INSERT
       INTO txn (id, number, created, type, person)
     SELECT id AS id,
            IFNULL(number, 0) AS number,
            date AS created,
            CASE type
              WHEN 1 THEN 'customer'
              WHEN 2 THEN 'vendor'
              WHEN 3 THEN 'internal'
            END AS type,
            id_item AS person
       FROM co.request
      WHERE id_parent IS NULL
     ON DUPLICATE KEY
     UPDATE
            person = VALUES(person)";
$r= $db->query($q) or die("query failed: ". $db->error);
echo "Loaded ", $db->affected_rows, " incomplete transactions.<br>";

# lines from requests (un-received items)
#
# needs the id offset to avoid collisions
$q= "INSERT IGNORE
       INTO txn_line (id, txn, line, item, ordered, shipped, allocated)
     SELECT co.id + 200000 AS id,
            co.id_parent AS txn,
            IFNULL(co.in_parent_index, 0) AS line,
            co.id_item AS item,
            IF(co.type = 1, -1, 1) * co.quantity AS ordered,
            IF(co.type = 1, -1, 1) * co.quantity AS shipped, 
            0 AS allocated
       FROM co.request co
      WHERE co.id_parent IS NOT NULL";
$r= $db->query($q) or die("query failed: ". $db->error);
echo "Loaded ", $db->affected_rows, " transaction lines from incomplete orders.<br>";

# basics
$q= "INSERT
       INTO txn (id, number, created, type, person)
     SELECT id_request AS id,
            IFNULL(IF(type = 2,
                      SUBSTRING_INDEX(formatted_request_number, '-', -1),
                      number),
                   0) AS number,
            date_request AS created,
            CASE type
              WHEN 1 THEN 'customer'
              WHEN 2 THEN 'vendor'
              WHEN 3 THEN 'internal'
            END AS type,
            id_item AS person
       FROM co.transaction
      WHERE id_parent IS NULL
     ON DUPLICATE KEY
     UPDATE
            person = VALUES(person)";
$r= $db->query($q) or die("query failed: ". $db->error);
echo "Loaded ", $db->affected_rows, " transactions.<br>";

# lines from transactions
$q= "INSERT
       INTO txn_line (id, txn, line, item, ordered, shipped, allocated)
     SELECT IF(co.id_request, co.id_request + 200000, co.id) AS id,
            tx.id_request AS txn,
            IFNULL(co.in_parent_index, 0) AS line,
            co.id_item AS item,
            IF(co.type = 1, -1, 1) * co.quantity AS ordered,
            0 AS shipped,
            IF(co.type = 1, -1, 1) * co.quantity AS allocated
       FROM co.transaction co
       JOIN co.transaction tx ON (tx.id = co.id_parent)
      WHERE co.id_parent IS NOT NULL
     ON DUPLICATE KEY
     UPDATE ordered = ordered + VALUES(ordered),
            allocated = allocated + VALUES(allocated)";
$r= $db->query($q) or die("query failed: ". $db->error);
echo "Loaded ", $db->affected_rows, " (or so) transaction lines.<br>";

