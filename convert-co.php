<?php
require 'scat.php';

if (!$_REQUEST['go']) {
  head("convert");
?>
<button id="go">Convert Checkout Data</button>
<script>
$('#go').on('click', function() {
  $('#go').attr('disabled', true);
  var label= $('#go').text();
  $('#go').text('Converting....');
  $.getJSON("convert-co.php?callback=?",
            { go: true },
            function (data) {
              $('#result').text('');
              $('#result').append(data.result);
              $('#result').slideDown();
              $('#go').text(label);
              $('#go').attr('disabled', false);
            });
});
</script>
<div id="result" style="display:none"></div>
<?
  foot();
  exit;
}

ob_start();
# ITEMS
#
# load item basics
$q= "INSERT INTO item (id, code, name, minimum_quantity, taxfree, active, deleted)
     SELECT id,
            CONCAT(IF(deleted = 't','DEL-',''), IFNULL((SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 15 ORDER BY id DESC LIMIT 1), id)) code,
            IFNULL((SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 2 ORDER BY id DESC LIMIT 1), id) name,
            IFNULL((SELECT minimum FROM co.stock WHERE id_product = item.id AND stocktype = 1),0) min,
            IFNULL((SELECT 0 FROM co.tax_group_item WHERE id_item = IFNULL(id_parent, id)),
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
$r= $db->query($q) or die_query($db, $q);
echo "Loaded ", $db->affected_rows, " items.<br>";

# load brands
$q= "INSERT IGNORE INTO brand (name)
     SELECT (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 14 ORDER BY id DESC LIMIT 1)
       FROM item";
$r= $db->query($q) or die_query($db, $q);
echo "Loaded ", $db->affected_rows, " brands.<br>";

$q= "UPDATE item SET
       brand = (SELECT id FROM brand WHERE brand.name = (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 14 ORDER BY id DESC LIMIT 1))";
$r= $db->query($q) or die_query($db, $q);
echo "Set brand on ", $db->affected_rows, " rows.<br>";

# load pricing
$q= "CREATE TEMPORARY TABLE co_pricing
     SELECT id,
            (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 16 ORDER BY id DESC LIMIT 1) description,
            (SELECT value FROM co.metanumber WHERE id_item = item.id AND id_metatype = 17 ORDER BY id DESC LIMIT 1) price
      FROM item";
$r= $db->query($q) or die_query($db, $q);
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
$r= $db->query($q) or die_query($db, $q);
echo "Updated pricing on ", $db->affected_rows, " rows.<br>";

# load barcodes
$q= "INSERT IGNORE INTO barcode (code, item)
     SELECT (SELECT value FROM co.metavalue WHERE id_item = item.id AND id_metatype = 13 ORDER BY id DESC LIMIT 1) AS code,
            id AS item
       FROM item";
$r= $db->query($q) or die_query($db, $q);
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
$r= $db->query($q) or die_query($db, $q);
echo "Loaded ", $db->affected_rows, " people.<br>";

# TRANSACTIONS
#

# completed transactions
$q= "INSERT
       INTO txn (id, number, created, filled, type, person, tax_rate)
     SELECT id_request AS id,
            IFNULL(IF(type = 2,
                      SUBSTRING_INDEX(formatted_request_number, '-', -1),
                      number),
                   0) AS number,
            date_request AS created,
            date AS filled,
            CASE type
              WHEN 1 THEN 'customer'
              WHEN 2 THEN 'vendor'
              WHEN 3 THEN 'correction'
            END AS type,
            id_item AS person,
            IF(has_tax = 't',
               (SELECT rate FROM co.metatax WHERE id = metataxstate),
               0) AS tax_rate
       FROM co.transaction
      WHERE id_parent IS NULL
     ON DUPLICATE KEY
     UPDATE
            created = VALUES(created),
            filled = VALUES(filled),
            person = VALUES(person),
            tax_rate = VALUES(tax_rate)";
$r= $db->query($q) or die_query($db, $q);
echo "Loaded ", $db->affected_rows, " transactions.<br>";

# incomplete transactions
$q= "INSERT
       INTO txn (id, number, created, type, person, tax_rate)
     SELECT id AS id,
            IFNULL(number, 0) AS number,
            date AS created,
            CASE type
              WHEN 1 THEN 'customer'
              WHEN 2 THEN 'vendor'
              WHEN 3 THEN 'correction'
            END AS type,
            id_item AS person,
            IF(has_tax = 't',
               (SELECT rate FROM co.metatax WHERE id = metataxstate),
               0) AS tax_rate
       FROM co.request
      WHERE id_parent IS NULL
     ON DUPLICATE KEY
     UPDATE
            filled = NULL,
            person = VALUES(person),
            tax_rate = VALUES(tax_rate)";
$r= $db->query($q) or die_query($db, $q);
echo "Loaded ", $db->affected_rows, " incomplete transactions.<br>";

/*
 * We have to dump the transaction lines because we're actually squashing
 * together lines that Checkout kept distinct when parts of a Purchase Order
 * was received at times. We have mixed feelings about that. (But in our data,
 * that was only ~37 items and we don't plan on having piecemeal order
 * receiving in Scat.)
 */

$q= "TRUNCATE txn_line";
$r= $db->query($q) or die_query($db, $q);
echo "Reset transaction lines.<br>";

# lines from transactions
$q= "INSERT
       INTO txn_line (id, txn, line, item, ordered, allocated, override_name, retail_price, discount_type, discount, taxfree)
     SELECT id_request AS id,
            (SELECT id_request FROM co.transaction parent
              WHERE parent.id = tx.id_parent) AS txn,
            IFNULL(in_parent_index, 0) AS line,
            id_item AS item,
            IF(type = 1, -1, 1) * quantity AS ordered,
            IF(type = 1, -1, 1) * quantity AS allocated,
            IF(overrides LIKE '%004name%', SUBSTRING_INDEX(SUBSTRING_INDEX(overrides, '\\\\000\\\\000\\\\000', -1), 'q\\\\003s', 1), NULL) AS override_name,
            IFNULL(override_price, (SELECT value FROM co.metanumber m WHERE id <= metanumberstate AND m.id_item = tx.id_item AND id_metatype = IF(type = 1, 17, 18) ORDER BY id DESC LIMIT 1)) retail_price,
            IF(discount_percentage, 'percentage', NULL) AS discount_type,
            IF(discount_percentage, discount_percentage, NULL) AS discount,
            (SELECT taxfree FROM item WHERE item.id = id_item) taxfree
       FROM co.transaction tx
      WHERE id_parent IS NOT NULL
     ON DUPLICATE KEY
     UPDATE ordered = ordered + VALUES(ordered),
            allocated = allocated + VALUES(allocated)";
$r= $db->query($q) or die_query($db, $q);
echo "Loaded ", $db->affected_rows, " (or so) transaction lines.<br>";

# lines from requests (un-received items)
#
$q= "INSERT
       INTO txn_line (id, txn, line, item, ordered, allocated, override_name, retail_price, discount_type, discount, taxfree)
     SELECT co.id AS id,
            co.id_parent AS txn,
            IFNULL(co.in_parent_index, 0) AS line,
            co.id_item AS item,
            IF(co.type = 1, -1, 1) * co.quantity AS ordered,
            0 AS allocated,
            IF(overrides LIKE '%name%', SUBSTRING_INDEX(SUBSTRING_INDEX(overrides, '012(V', -1), '\\\\012p3', 1), NULL) AS override_name,
            IFNULL(override_price, (SELECT value FROM co.metanumber m WHERE id <= metanumberstate AND m.id_item = co.id_item AND id_metatype = IF(type = 1, 17, 18) ORDER BY id DESC LIMIT 1)) retail_price,
            IF(discount_percentage, 'percentage', NULL) AS discount_type,
            IF(discount_percentage, discount_percentage, NULL) AS discount,
            (SELECT taxfree FROM item WHERE item.id = id_item) taxfree
       FROM co.request co
      WHERE co.id_parent IS NOT NULL
     ON DUPLICATE KEY
     UPDATE ordered = ordered + VALUES(ordered)";
$r= $db->query($q) or die_query($db, $q);
echo "Loaded ", $db->affected_rows, " transaction lines from incomplete orders.<br>";

# payments
#
$q= "INSERT IGNORE
       INTO payment (id, txn, method, amount, processed)
     SELECT id_payment AS id,
            (SELECT id_request FROM co.transaction
              WHERE id_transaction = transaction.id) AS txn,
            CASE type
              WHEN 1 THEN 'credit' /* online */
              WHEN 2 THEN 'credit'
              WHEN 3 THEN 'credit' /* debit */
              WHEN 4 THEN 'check'
              WHEN 5 THEN 'cash'
              WHEN 6 THEN 'change'
              WHEN 7 THEN 'gift'
            END AS method,
            txn.amount AS amount,
            date AS processed
       FROM co.payment_transaction txn
       JOIN co.payment ON (id_payment = payment.id)";
$r= $db->query($q) or die_query($db, $q);
echo "Loaded ", $db->affected_rows, " payments.<br>";

# notes
#
$q= "INSERT IGNORE INTO txn_note (id, txn, entered, content)
     SELECT note.id, id_transaction, date, content
       FROM co.note_transaction 
       JOIN co.note ON (note.id = id_note)";
$r= $db->query($q) or die_query($db, $q);
echo "Loaded ", $db->affected_rows, " notes.<br>";

# figure out paid transactions
#
$q= "CREATE TEMPORARY TABLE txn_paid
     SELECT id,
            untaxed, taxed, tax_rate,
            CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                 AS DECIMAL(9,2)) total,
            paid,
            last_payment
      FROM (SELECT
            txn.id,
            CAST(ROUND_TO_EVEN(
              SUM(IF(txn_line.taxfree, 1, 0) *
                IF(type = 'customer', -1, 1) * allocated *
                CASE discount_type
                  WHEN 'percentage' THEN retail_price * ((100 - discount) / 100)
                  WHEN 'relative' THEN (retail_price - discount) 
                  WHEN 'fixed' THEN (discount)
                  ELSE retail_price
                END),
              2) AS DECIMAL(9,2))
            untaxed,
            CAST(ROUND_TO_EVEN(
              SUM(IF(txn_line.taxfree, 0, 1) *
                IF(type = 'customer', -1, 1) * allocated *
                CASE discount_type
                  WHEN 'percentage' THEN retail_price * ((100 - discount) / 100)
                  WHEN 'relative' THEN (retail_price - discount) 
                  WHEN 'fixed' THEN (discount)
                  ELSE retail_price
                END),
              2) AS DECIMAL(9,2))
            taxed,
            tax_rate,
            CAST((SELECT SUM(amount) FROM payment WHERE txn.id = payment.txn)
                 AS DECIMAL(9,2)) AS paid,
            (SELECT MAX(processed)
               FROM payment WHERE payment.txn = txn.id) AS last_payment
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
      WHERE type = 'customer'
      GROUP BY txn.id) t";
$r= $db->query($q) or die_query($db, $q);

$q= "UPDATE txn, txn_paid SET txn.paid = last_payment
      WHERE txn.id = txn_paid.id AND total - txn_paid.paid < 0.02";
$r= $db->query($q) or die_query($db, $q);
echo "Noted ", $db->affected_rows, " payments.<br>";

$out= ob_get_contents();
ob_end_clean();

echo generate_jsonp(array("result" => $out));
