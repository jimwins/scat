<?

function txn_load_full($db, $id) {
  $txn= txn_load($db, $id);
  $items= txn_load_items($db, $id);
  $payments= txn_load_payments($db, $id);
  $notes= txn_load_notes($db, $id);

  return array('txn' => $txn,
               'items' => $items,
               'payments' => $payments,
               'notes' => $notes);
}

function txn_load($db, $id) {
  $q= "SELECT id, type,
              number, created, filled, paid, returned_from,
              CONCAT(DATE_FORMAT(created, '%Y-'), number) AS formatted_number,
              person, person_name,
              IFNULL(ordered, 0) ordered, allocated,
              taxed, untaxed,
              CAST(tax_rate AS DECIMAL(9,2)) tax_rate, 
              taxed + untaxed subtotal,
              CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                   AS DECIMAL(9,2)) total,
              IFNULL(total_paid, 0.00) total_paid
        FROM (SELECT
              txn.id, txn.type, txn.number,
              txn.created, txn.filled, txn.paid,
              txn.returned_from,
              txn.person,
              CONCAT(IFNULL(person.name, ''),
                     IF(person.name != '' AND person.company != '', ' / ', ''),
                     IFNULL(person.company, ''))
                  AS person_name,
              SUM(ordered) * IF(txn.type = 'customer', -1, 1) AS ordered,
              SUM(allocated) * IF(txn.type = 'customer', -1, 1) AS allocated,
              CAST(ROUND_TO_EVEN(
                SUM(IF(txn_line.taxfree, 1, 0) *
                  IF(type = 'customer', -1, 1) * ordered *
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
                  IF(type = 'customer', -1, 1) * ordered *
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
                   AS DECIMAL(9,2)) AS total_paid
         FROM txn
         LEFT JOIN txn_line ON (txn.id = txn_line.txn)
         LEFT JOIN person ON (txn.person = person.id)
        WHERE txn.id = $id) t";

  $r= $db->query($q)
    or die_query($db, $q);

  $txn= $r->fetch_assoc();
  $txn['subtotal']= (float)$txn['subtotal'];
  $txn['total']= (float)$txn['total'];
  $txn['total_paid']= (float)$txn['total_paid'];

  return $txn;
}

function txn_load_items($db, $id) {
  $q= "SELECT
              txn_line.id AS line_id, item.code,
              IFNULL(override_name, item.name) name,
              txn_line.retail_price msrp,
              IF(txn_line.discount_type,
                 CASE txn_line.discount_type
                   WHEN 'percentage' THEN CAST(ROUND_TO_EVEN(txn_line.retail_price * ((100 - txn_line.discount) / 100), 2) AS DECIMAL(9,2))
                   WHEN 'relative' THEN (txn_line.retail_price - txn_line.discount) 
                   WHEN 'fixed' THEN (txn_line.discount)
                 END,
                 txn_line.retail_price) price,
              IFNULL(CONCAT('MSRP $', txn_line.retail_price, ' / Sale: ',
                            CASE txn_line.discount_type
                WHEN 'percentage' THEN CONCAT(ROUND(txn_line.discount), '% off')
                WHEN 'relative' THEN CONCAT('$', txn_line.discount, ' off')
              END), '') discount,
              ordered * IF(txn.type = 'customer', -1, 1) AS quantity,
              allocated * IF(txn.type = 'customer', -1, 1) AS allocated,
              (SELECT SUM(allocated) FROM txn_line WHERE item = item.id) AS stock
         FROM txn
         LEFT JOIN txn_line ON (txn.id = txn_line.txn)
         JOIN item ON (txn_line.item = item.id)
        WHERE txn.id = $id
        ORDER BY line ASC";

  $r= $db->query($q)
    or die_query($db, $q);

  $items= array();
  while ($row= $r->fetch_assoc()) {
    /* force numeric values to numeric type */
    $row['msrp']= (float)$row['msrp'];
    $row['price']= (float)$row['price'];
    $row['quantity']= (int)$row['quantity'];
    $row['stock']= (int)$row['stock'];
    $items[]= $row;
  }

  return $items;
}

function txn_load_payments($db, $id) {
  $q= "SELECT id, processed, method, amount, discount
         FROM payment
        WHERE txn = $id
        ORDER BY processed ASC";

  $r= $db->query($q)
    or die_query($db, $q);

  $payments= array();
  while ($row= $r->fetch_assoc()) {
    /* force numeric values to numeric type */
    $row['amount']= (float)$row['amount'];
    $row['discount']= (float)$row['discount'];
    $payments[]= $row;
  }

  return $payments;
}

function txn_load_notes($db, $id) {
  $q= "SELECT id, entered, content
         FROM txn_note
        WHERE txn = $id
        ORDER BY entered ASC";

  $r= $db->query($q)
    or die_query($db, $q);

  $payments= array();
  while ($row= $r->fetch_assoc()) {
    $payments[]= $row;
  }

  return $payments;
}
