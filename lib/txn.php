<?php
include dirname(__FILE__).'/person.php';

function txn_load_full($db, $id) {
  $txn= txn_load($db, $id);
  $items= txn_load_items($db, $id);
  $payments= txn_load_payments($db, $id);
  $notes= txn_load_notes($db, $id);
  $shipments= txn_load_shipments($db, $id);
  $person= person_load($db, $txn['person_id'], PERSON_FIND_EMPTY);
  if ($txn['shipping_address_id']) {
    $shipping_address= txn_load_address($db, $txn['shipping_address_id']);
  }

  return array('txn' => $txn,
               'items' => $items,
               'payments' => $payments,
               'person' => $person,
               'notes' => $notes,
               'shipments' => $shipments,
               'shipping_address' => $shipping_address);
}

function txn_load($db, $id) {
  $q= "SELECT id, uuid, online_sale_id, type,
              number, status, created, filled, paid, returned_from_id,
              no_rewards,
              IF(type = 'vendor' && YEAR(created) > 2013,
                 CONCAT(SUBSTRING(YEAR(created), 3, 2), number),
                 CONCAT(DATE_FORMAT(created, '%Y-'), number))
                AS formatted_number,
              person_id, person_name,
              shipping_address_id,
              IFNULL(ordered, 0) ordered, allocated,
              taxed, untaxed,
              CAST(tax_rate AS DECIMAL(9,2)) tax_rate, 
              taxed + untaxed subtotal,
              IF(uuid IS NOT NULL, /* Tax calculated per-line */
                 tax,
                 CAST(ROUND_TO_EVEN(taxed * (tax_rate / 100), 2)
                      AS DECIMAL(9,2))) AS tax,
              IF(uuid IS NOT NULL,
                 taxed + untaxed + tax,
                 CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                      AS DECIMAL(9,2))) total,
              IFNULL(total_paid, 0.00) total_paid
        FROM (SELECT
              txn.id, txn.uuid, txn.online_sale_id,
              txn.type, txn.number, txn.status,
              txn.created, txn.filled, txn.paid,
              txn.returned_from_id, txn.no_rewards,
              txn.person_id,
              CONCAT(IFNULL(person.name, ''),
                     IF(person.name != '' AND person.company != '', ' / ', ''),
                     IFNULL(person.company, ''))
                  AS person_name,
              txn.shipping_address_id,
              SUM(ordered) * IF(txn.type = 'customer', -1, 1) AS ordered,
              SUM(allocated) * IF(txn.type = 'customer', -1, 1) AS allocated,
              CAST(ROUND_TO_EVEN(
                SUM(IF(txn_line.taxfree, 1, 0) *
                  IF(type = 'customer', -1, 1) * ordered *
                  sale_price(retail_price, discount_type, discount)),
                2) AS DECIMAL(9,2))
              untaxed,
              CAST(ROUND_TO_EVEN(
                SUM(IF(txn_line.taxfree, 0, 1) *
                  IF(type = 'customer', -1, 1) * ordered *
                  sale_price(retail_price, discount_type, discount)),
                2) AS DECIMAL(9,2))
              taxed,
              tax_rate,
              SUM(tax) AS tax,
              CAST((SELECT SUM(amount) FROM payment WHERE txn.id = payment.txn_id)
                   AS DECIMAL(9,2)) AS total_paid
         FROM txn
         LEFT JOIN txn_line ON (txn.id = txn_line.txn_id)
         LEFT JOIN person ON (txn.person_id = person.id)
        WHERE txn.id = $id) t";

  $r= $db->query($q)
    or die_query($db, $q);

  $txn= $r->fetch_assoc();
  $txn['taxed']= (float)$txn['taxed'];
  $txn['untaxed']= (float)$txn['untaxed'];
  $txn['subtotal']= (float)$txn['subtotal'];
  $txn['total']= (float)$txn['total'];
  $txn['total_paid']= (float)$txn['total_paid'];
  $txn['no_rewards']= (int)$txn['no_rewards'];

  return $txn;
}

function txn_load_items($db, $id) {
  $q= "SELECT
              txn_line.id AS line_id, item.code, item.id AS item_id,
              IF(type = 'vendor' && txn.person_id,
                 (SELECT vendor_sku
                    FROM vendor_item
                   WHERE vendor_id = txn.person_id
                     AND vendor_item.item_id = txn_line.item_id
                     AND vendor_item.active
                   ORDER BY vendor_item.purchase_quantity <= txn_line.ordered
                   LIMIT 1),
                 NULL) vendor_sku,
              IFNULL(override_name, item.name) name, data,
              txn_line.retail_price msrp,
              sale_price(txn_line.retail_price, txn_line.discount_type,
                         txn_line.discount) price,
              (IF(type = 'customer', -1, 1) * ordered *
               sale_price(txn_line.retail_price, txn_line.discount_type,
                          txn_line.discount)) AS ext_price,
              IFNULL(CONCAT(IF(item.retail_price, 'MSRP $', 'List $'),
                            txn_line.retail_price,
                            CASE txn_line.discount_type
                            WHEN 'percentage' THEN
                              CONCAT(' / Sale: ',
                                     ROUND(txn_line.discount), '% off')
                            WHEN 'relative' THEN
                              CONCAT(' / Sale: $', txn_line.discount, ' off')
                            WHEN 'fixed' THEN
                              ''
                            END), '') discount,
              ordered * IF(txn.type = 'customer', -1, 1) AS quantity,
              allocated * IF(txn.type = 'customer', -1, 1) AS allocated,
              (SELECT SUM(allocated) FROM txn_line WHERE item_id = item.id) AS stock,
              purchase_quantity
         FROM txn
         LEFT JOIN txn_line ON (txn.id = txn_line.txn_id)
         JOIN item ON (txn_line.item_id = item.id)
        WHERE txn.id = $id
        ORDER BY txn_line.id ASC";

  $r= $db->query($q)
    or die_query($db, $q);

  $items= array();
  while ($row= $r->fetch_assoc()) {
    /* force numeric values to numeric type */
    $row['msrp']= (float)$row['msrp'];
    $row['price']= (float)$row['price'];
    $row['quantity']= (int)$row['quantity'];
    $row['stock']= (int)$row['stock'];

    $row['fake_barcode']= generate_upc(sprintf("4004%07d", $row['item_id']));

    if ($row['data']) {
      $row['data']= json_decode($row['data']);
    }

    $items[]= $row;
  }

  return $items;
}

function txn_load_payments($db, $id) {
  $q= "SELECT id, processed, method, amount, discount,
              cc_txn, cc_approval, cc_lastfour, cc_expire, cc_type
         FROM payment
        WHERE txn_id = $id
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
  $q= "SELECT id, added, content, public
         FROM note
        WHERE kind = 'txn' AND attach_id = $id
        ORDER BY added ASC";

  $r= $db->query($q)
    or die_query($db, $q);

  $notes= array();
  while ($row= $r->fetch_assoc()) {
    $row['public']= (int)$row['public'];
    $notes[]= $row;
  }

  return $notes;
}

function txn_load_address($db, $id) {
  $q= "SELECT *
         FROM address
        WHERE id = $id";

  $r= $db->query($q)
    or die_query($db, $q);

  return $r ? $r->fetch_assoc() : null;
}

function txn_load_shipments($db, $id) {
  $q= "SELECT *
         FROM shipment
        WHERE txn_id = $id
        ORDER BY created ASC";

  $r= $db->query($q)
    or die_query($db, $q);

  $shipments= array();
  while ($row= $r->fetch_assoc()) {
    $shipments[]= $row;
  }

  return $shipments;
}

function txn_apply_discounts($db, $id) {
  $txn= txn_load($db, $id);

  if (!$txn) {
    // XXX better error handling
    return false;
  }

  // Not an error, but we don't do anything
  if ($txn['type'] != 'customer') {
    return true;
  }

  $q= "SELECT pattern, ANY_VALUE(pattern_type) pattern_type,
              GROUP_CONCAT(minimum_quantity ORDER BY minimum_quantity
                           SEPARATOR ',') breaks,
              GROUP_CONCAT(discount_type ORDER BY minimum_quantity
                           SEPARATOR ',') discount_types,
              GROUP_CONCAT(discount ORDER BY minimum_quantity
                           SEPARATOR ',') discounts
         FROM price_override
        GROUP BY pattern";

  $discounts= $db->query($q)
    or die_query($db, $q);

  foreach ($discounts as $d) {
    if ($d['pattern_type'] == 'product') {
      $condition= "product_id = '{$d['pattern']}'";
    } else {
      $condition= "code {$d['pattern_type']} '{$d['pattern']}'";
    }
    $count= $db->get_one("SELECT ABS(SUM(ordered))
                            FROM txn_line
                            JOIN item ON txn_line.item_id = item.id
                           WHERE txn_id = $id
                             AND $condition
                             AND NOT discount_manual");

    if (!$count) {
      continue;
    }

    $new_discount= 0;
    $new_discount_type= '';

    $breaks= explode(',', $d['breaks']);
    $discount_types= explode(',', $d['discount_types']);
    $discounts= explode(',', $d['discounts']);

    foreach ($breaks as $i => $qty) {
      if ($count >= $qty) {
        $new_discount_type= $discount_types[$i];
        $new_discount= $discounts[$i];
      }
    }

    if ($new_discount) {
      if ($new_discount_type != 'additional_percentage') {
        $q= "UPDATE txn_line, item
                SET txn_line.discount = $new_discount,
                    txn_line.discount_type = '$new_discount_type'
              WHERE txn_id = $id AND txn_line.item_id = item.id
                AND $condition
                AND NOT discount_manual";
      } else {
        $q= "UPDATE txn_line, item
                SET txn_line.discount =
                      sale_price(sale_price(item.retail_price,
                                            item.discount_type,
                                            item.discount),
                                 'percentage',
                                 $new_discount),
                    txn_line.discount_type = 'fixed'
              WHERE txn_id = $id AND txn_line.item_id = item.id
                AND $condition
                AND NOT discount_manual";
      }
    } else {
      $q= "UPDATE txn_line, item
              SET txn_line.discount = item.discount,
                  txn_line.discount_type = item.discount_type 
            WHERE txn_id = $id AND txn_line.item_id = item.id
              AND $condition
              AND NOT discount_manual";
    }

    $db->query($q)
      or die_query($db, $q);
  }

  $payments= txn_load_payments($db, $id);
  foreach ($payments as $payment) {
    if ($payment['method'] == 'discount' && $payment['discount']) {
      // Reload the txn so we have updated total
      $txn= txn_load($db, $id);

      $q= "UPDATE payment
              SET amount = ($payment[discount] / 100) * $txn[total]
            WHERE id = $payment[id]";

      $db->query($q)
        or die_query($db, $q);
    }
  }

  return true;
}

function txn_update_filled($db, $txn_id) {
  /* If everything is allocated, flag txn as filled. */
  $q= "SELECT COUNT(*)
         FROM txn_line
        WHERE txn_id = $txn_id AND ordered != allocated";

  $unfilled= $db->get_one($q);

  $q= "UPDATE txn
          SET filled = IF($unfilled, NULL, NOW()),
              status = IF($unfilled && status = 'filled',
                          'new',
                          IF(status = 'new',
                             'filled',
                             status))
        WHERE id = $txn_id";
  $r= $db->query($q)
    or die_query($db, $q);

  return true;
}

class Transaction {
  private $db;
  private $data;

  public $id;

  public function __construct($db, $id= null) {
    $this->db= $db;
    $this->id= $id;

    if ($id)
      $this->data= txn_load_full($db, $this->id);
  }

  public function __set($name, $value) {
    $this->data['txn'][$name]= $value;
  }

  public function __get($name) {
    if ($name == 'person_details') {
      return $this->data['person'];
    }
    if ($name == 'items') {
      return $this->data['items'];
    }
    if ($name == 'payments') {
      return $this->data['payments'];
    }
    if ($name == 'notes') {
      return $this->data['notes'];
    }
    return $this->data['txn'][$name];
  }

  public function __isset($name) {
    return isset($this->data['txn'][$name]);
  }

  public function __unset($name) {
    unset($this->data['txn'][$name]);
  }

  public function hasItems() {
    return !!count($this->data['items']);
  }

  public function hasPayments() {
    return !!count($this->data['payments']);
  }

  public function canPay($method, $amount) {
    // only 'gift' and 'cash' allow giving change
    $change= (($method == 'cash' || $method == 'gift') ? true : false);

    if (!$change &&
        (($this->total >= 0 &&
          bccomp(bcadd($amount, $this->total_paid), $this->total) > 0)
         ||
         ($this->total < 0 &&
          bccomp(bcadd($amount, $this->total_paid), $this->total) < 0)))
    {
      return false;
    }

    return true;
  }

  public function addPayment($method, $amount, $extra) {
    // only 'gift' and 'change' allow giving change
    $change= (($method == 'cash' || $method == 'gift') ? true : false);

    if (!$this->canPay($method, $amount)) {
      throw new Exception("Amount is too much.");
    }

    $this->db->start_transaction();

    $extra_fields= "";
    foreach ($extra as $key => $value) {
      $extra_fields.= "$key = '" . $this->db->escape($value) . "', ";
    }

    // add payment record
    $q= "INSERT INTO payment
            SET txn_id = {$this->id}, method = '$method', amount = $amount,
            $extra_fields
            processed = NOW()";
    $r= $this->db->query($q)
      or die_query($this->db, $q);

    $payment= $this->db->insert_id;

    // if total > 0 and amount + paid > total, add change record
    $change_paid= 0.0;
    if ($change && $this->total > 0 &&
        bccomp(bcadd($amount, $this->total_paid), $this->total) > 0) {
      $change_paid= bcsub($this->total, bcadd($amount, $this->total_paid));

      $q= "INSERT INTO payment
              SET txn_id = {$this->id}, method = 'change', amount = $change_paid,
              processed = NOW()";
      $r= $this->db->query($q)
        or die_query($this->db, $q);
    }

    $this->total_paid= bcadd($this->total_paid, bcadd($amount, $change_paid));

    // if we're all paid up, record that the txn is paid
    // XXX goes straight to complete because this is only used by old API
    if (!bccomp($this->total_paid, $this->total)) {
      $q= "UPDATE txn
              SET paid = NOW(),
                  status = IF(status IN ('new', 'filled'), 'complete', status)
            WHERE id = {$this->id}";
      $r= $this->db->query($q)
        or die_query($this->db, $q);

      $this->rewardLoyalty();

    } elseif ($this->paid) {
      // we thought we were paid, but now we must not be
      $q= "UPDATE txn SET paid = NULL, status = IF(filled, 'filled', 'new') WHERE id = {$this->id}";
      $r= $this->db->query($q)
        or die_query($this->db, $q);
    }

    $this->db->commit();

    return $payment;
  }

  public function removePayment($payment, $override) {
    if ($this->paid && !$override)
      throw new Exception("Transaction is fully paid, can't remove payments.");

    $this->db->start_transaction()
      or die_query($this->db, "START TRANSACTION");

    // remove payment record
    $q= "DELETE FROM payment WHERE id = $payment AND txn_id = {$this->id}";
    $r= $this->db->query($q)
      or die_query($this->db, $q);

    if ($this->paid) {
      $q= "UPDATE txn SET paid = NULL, status = IF(filled, 'filled', 'new') WHERE id = {$this->id}";
      $r= $this->db->query($q)
        or die_query($this->db, $q);
    }

    // remove loyalty records
    $this->clearLoyalty();

    $this->db->commit()
      or die_query($this->db, "COMMIT");

    return true;
  }

  public function rewardLoyalty() {
    // No person? No loyalty.
    if (!$this->person_id)
      return;

    // Use rewards
    $q= "INSERT INTO loyalty (txn_id, person_id, processed, note, points)
         SELECT {$this->id} txn_id,
                {$this->person_id} person_id,
                NOW() processed,
                name note,
                cost * allocated points
           FROM loyalty_reward
           JOIN txn_line ON loyalty_reward.item_id = txn_line.item_id
           JOIN item ON txn_line.item_id = item.id
          WHERE txn_line.txn_id = {$this->id}";
    // XXX throw an exception on failure
    $r= $this->db->query($q)
        or die_query($this->db, $q);

    // No rewards for this txn?
    if ($this->no_rewards)
      return;

    // Award new points
    $points= (int)$this->taxed *
              (defined('LOYALTY_MULTIPLIER') ? LOYALTY_MULTIPLIER : 1);
    if ($points == 0 && $this->taxed > 0) $points= 1;

    $q= "INSERT INTO loyalty
            SET txn_id= {$this->id},
                person_id = {$this->person_id},
                processed = NOW(),
                note = 'Pt Earned',
                points = $points";
    // XXX throw an exception on failure
    $r= $this->db->query($q)
        or die_query($this->db, $q);
  }

  public function clearLoyalty() {
    $q= "DELETE FROM loyalty
          WHERE txn_id = {$this->id}";
    // XXX throw an exception on failure
    $r= $this->db->query($q)
        or die_query($this->db, $q);
  }
}
