<?php
require 'scat.php';

head("convert");

# ITEMS
#
# load item basics
$q= "INSERT INTO item (id, code, name, minimum_quantity, taxfree, active, deleted)
     SELECT id,
            (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 15 ORDER BY id DESC LIMIT 1) code,
            (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 2 ORDER BY id DESC LIMIT 1) name,
            (SELECT minimum FROM co.stock WHERE id_product = item.id AND stocktype = 1) min,
            IFNULL((SELECT 0 FROM co.tax_group_item WHERE id_item = id),
                   1) taxfree,
            (active = 't') active,
            (deleted = 't') deleted
       FROM co.item
      WHERE type = 2
     ON DUPLICATE KEY
     UPDATE code = VALUES(code),
            name = VALUES(name),
            minimum_quantity = VALUES(minimum_quantity),
            taxfree = VALUES(taxfree),
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
       INTO txn (id, number, created, type, person, tax_rate)
     SELECT id + 200000 AS id,
            IFNULL(number, 0) AS number,
            date AS created,
            CASE type
              WHEN 1 THEN 'customer'
              WHEN 2 THEN 'vendor'
              WHEN 3 THEN 'internal'
            END AS type,
            id_item AS person,
            (SELECT rate FROM co.metatax WHERE id = metataxstate) AS tax_rate
       FROM co.request
      WHERE id_parent IS NULL
     ON DUPLICATE KEY
     UPDATE
            person = VALUES(person),
            tax_rate = VALUES(tax_rate)";
$r= $db->query($q) or die("query failed: ". $db->error);
echo "Loaded ", $db->affected_rows, " incomplete transactions.<br>";

# lines from requests (un-received items)
#
# needs the id offset to avoid collisions
$q= "INSERT
       INTO txn_line (id, txn, line, item, ordered, shipped, allocated, override_name, retail_price, discount_type, discount, taxfree)
     SELECT co.id + 200000 AS id,
            co.id_parent + 200000 AS txn,
            IFNULL(co.in_parent_index, 0) AS line,
            co.id_item AS item,
            IF(co.type = 1, -1, 1) * co.quantity AS ordered,
            IF(co.type = 1, -1, 1) * co.quantity AS shipped, 
            0 AS allocated,
            IF(overrides LIKE '%name%', SUBSTRING_INDEX(SUBSTRING_INDEX(overrides, '012(V', -1), '\\\\012p3', 1), NULL) AS override_name,
            IF(override_price, override_price, (SELECT value FROM co.metanumber m WHERE id <= metanumberstate AND m.id_item = co.id_item AND id_metatype = IF(type = 1, 17, 18) ORDER BY id DESC LIMIT 1)) retail_price,
            IF(discount_percentage, 'percentage', NULL) AS discount_type,
            IF(discount_percentage, discount_percentage, NULL) AS discount,
            (SELECT taxfree FROM item WHERE item.id = id_item) taxfree
       FROM co.request co
      WHERE co.id_parent IS NOT NULL";
$r= $db->query($q) or die("query failed: ". $db->error);
echo "Loaded ", $db->affected_rows, " transaction lines from incomplete orders.<br>";

# basics
$q= "INSERT
       INTO txn (id, number, created, type, person, tax_rate)
     SELECT id AS id,
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
            id_item AS person,
            (SELECT rate FROM co.metatax WHERE id = metataxstate) AS tax_rate
       FROM co.transaction
      WHERE id_parent IS NULL
     ON DUPLICATE KEY
     UPDATE
            person = VALUES(person),
            tax_rate = VALUES(tax_rate)";
$r= $db->query($q) or die("query failed: ". $db->error);
echo "Loaded ", $db->affected_rows, " transactions.<br>";

# lines from transactions
$q= "INSERT
       INTO txn_line (id, txn, line, item, ordered, shipped, allocated, override_name, retail_price, discount_type, discount, taxfree)
     SELECT id AS id,
            id_parent AS txn,
            IFNULL(in_parent_index, 0) AS line,
            id_item AS item,
            IF(type = 1, -1, 1) * quantity AS ordered,
            0 AS shipped,
            IF(type = 1, -1, 1) * quantity AS allocated,
            IF(overrides LIKE '%004name%', SUBSTRING_INDEX(SUBSTRING_INDEX(overrides, '\\\\000\\\\000\\\\000', -1), 'q\\\\003s', 1), NULL) AS override_name,
            (SELECT value FROM co.metanumber m WHERE id <= metanumberstate AND m.id_item = tx.id_item AND id_metatype = IF(type = 1, 17, 18) ORDER BY id DESC LIMIT 1) retail_price,
            IF(discount_percentage, 'percentage', NULL) AS discount_type,
            IF(discount_percentage, discount_percentage, NULL) AS discount,
            (SELECT taxfree FROM item WHERE item.id = id_item) taxfree
       FROM co.transaction tx
      WHERE id_parent IS NOT NULL
     ON DUPLICATE KEY
     UPDATE ordered = ordered + VALUES(ordered),
            allocated = allocated + VALUES(allocated)";
$r= $db->query($q) or die("query failed: ". $db->error);
echo "Loaded ", $db->affected_rows, " (or so) transaction lines.<br>";

# figure out discounts
$q= "UPDATE txn_line, co.transaction tx
        SET retail_price = IF(retail_price, retail_price, override_price),
            discount_type = IFNULL(discount_type, IF(retail_price && retail_price != override_price, 'fixed', NULL)),
            discount = IFNULL(discount, IF(retail_price && retail_price != override_price, override_price, NULL))
     WHERE txn_line.id = tx.id AND override_price IS NOT NULL";

$r= $db->query($q) or die("query failed: ". $db->error);
echo "Updated ", $db->affected_rows, " prices on transaction lines.<br>";

# payments
#
$q= "INSERT IGNORE
       INTO payment (id, txn, method, amount, processed)
     SELECT id_payment AS id,
            id_transaction AS txn,
            CASE type
              WHEN 1 THEN 'credit' /* online */
              WHEN 2 THEN 'credit'
              WHEN 3 THEN 'credit' /* debit */
              WHEN 4 THEN 'check'
              WHEN 5 THEN 'cash'
              WHEN 6 THEN 'change'
              WHEN 7 THEN 'gift'
            END AS method,
            payment.amount AS amount,
            date AS processed
       FROM co.payment_transaction
       JOIN co.payment ON (id_payment = payment.id)";
$r= $db->query($q) or die("query failed: ". $db->error);
echo "Loaded ", $db->affected_rows, " payments.<br>";
