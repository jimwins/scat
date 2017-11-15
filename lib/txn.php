<?
include dirname(__FILE__).'/person.php';

function txn_load_full($db, $id) {
  $txn= txn_load($db, $id);
  $items= txn_load_items($db, $id);
  $payments= txn_load_payments($db, $id);
  $notes= txn_load_notes($db, $id);
  $person= person_load($db, $txn['person'], PERSON_FIND_EMPTY);

  return array('txn' => $txn,
               'items' => $items,
               'payments' => $payments,
               'person' => $person,
               'notes' => $notes);
}

function txn_load($db, $id) {
  $q= "SELECT id, type,
              number, created, filled, paid, returned_from, special_order,
              no_rewards,
              IF(type = 'vendor' && YEAR(created) > 2013,
                 CONCAT(SUBSTRING(YEAR(created), 3, 2), number),
                 CONCAT(DATE_FORMAT(created, '%Y-'), number))
                AS formatted_number,
              person, person_name,
              IFNULL(ordered, 0) ordered, allocated,
              taxed, untaxed,
              CAST(tax_rate AS DECIMAL(9,2)) tax_rate, 
              taxed + untaxed subtotal,
              CAST(ROUND_TO_EVEN(taxed * (tax_rate / 100), 2)
                   AS DECIMAL(9,2)) tax,
              CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                   AS DECIMAL(9,2)) total,
              IFNULL(total_paid, 0.00) total_paid
        FROM (SELECT
              txn.id, txn.type, txn.number,
              txn.created, txn.filled, txn.paid,
              txn.returned_from, txn.special_order, txn.no_rewards,
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
  $txn['special_order']= (int)$txn['special_order'];

  return $txn;
}

function txn_load_items($db, $id) {
  $q= "SELECT
              txn_line.id AS line_id, item.code, item.id AS item_id,
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
              (SELECT SUM(allocated) FROM txn_line WHERE item = item.id) AS stock,
              purchase_quantity
         FROM txn
         LEFT JOIN txn_line ON (txn.id = txn_line.txn)
         JOIN item ON (txn_line.item = item.id)
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
  $q= "SELECT id, entered, content, public
         FROM txn_note
        WHERE txn = $id
        ORDER BY entered ASC";

  $r= $db->query($q)
    or die_query($db, $q);

  $notes= array();
  while ($row= $r->fetch_assoc()) {
    $row['public']= (int)$row['public'];
    $notes[]= $row;
  }

  return $notes;
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

  // XXX store this somewhere else, obviously
  $discounts= array(
    '^AAM-[^S].*' => array('type' => 'RLIKE', 6 => '2.59', 12 => '2.39'),
    'MXG-%'  => array(12 => '6.79', 36 => '6.49', 72 => '5.99,SO'),
    'MXB-%'  => array(12 => '5.99', 36 => '5.49', 72 => '4.99,SO'),
    'MTEX014%' => array(12 => '6.49', 36 => '5.99', 72 => '4.99,SO'),
    'MTEX019%' => array(12 => '8.25', 36 => '7.49', 72 => '6.99,SO'),
    'SKXSDK%'=> array(12 => '2.49'),
    '^TB56[56].*'=> array('type' => 'RLIKE', 12 => '2.69'),
    'DA40286%'=>array(10 => '0.79', 100 => '0.69'),
    '^DA(1600|6032).*'=> array('type' => 'RLIKE', 12 => 5.99),
  );

  foreach ($discounts as $code => $breaks) {
    $like= isset($breaks['type']) ? $breaks['type'] : 'LIKE';
    $count= $db->get_one("SELECT ABS(SUM(ordered))
                            FROM txn_line
                            JOIN item ON txn_line.item = item.id
                           WHERE txn = $id
                             AND code $like '$code'
                             AND NOT discount_manual");

    $new_discount= 0;

    foreach ($breaks as $qty => $discount) {
      list($discount, $flags)= explode(',', $discount);
      if ($qty != 'type' &&
          $count >= $qty &&
          ($flags != 'SO' || $txn['special_order']) &&
          (!$new_discount || $discount < $new_discount)) {
        $new_discount= $discount;
      }
    }

    if ($new_discount) {
      $q= "UPDATE txn_line, item
              SET txn_line.discount = $new_discount,
                  txn_line.discount_type = 'fixed'
            WHERE txn = $id AND txn_line.item = item.id
              AND code $like '$code'
              AND NOT discount_manual";
    } else {
      $q= "UPDATE txn_line, item
              SET txn_line.discount = item.discount,
                  txn_line.discount_type = item.discount_type
            WHERE txn = $id AND txn_line.item = item.id
              AND code $like '$code'
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
        WHERE txn = $txn_id AND ordered != allocated";

  $unfilled= $db->get_one($q);

  $q= "UPDATE txn SET filled = IF($unfilled, NULL, NOW()) WHERE id = $txn_id";
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
            SET txn = {$this->id}, method = '$method', amount = $amount,
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
              SET txn = {$this->id}, method = 'change', amount = $change_paid,
              processed = NOW()";
      $r= $this->db->query($q)
        or die_query($this->db, $q);
    }

    $this->total_paid= bcadd($this->total_paid, bcadd($amount, $change_paid));

    // if we're all paid up, record that the txn is paid
    if (!bccomp($this->total_paid, $this->total)) {
      $q= "UPDATE txn SET paid = NOW() WHERE id = {$this->id}";
      $r= $this->db->query($q)
        or die_query($this->db, $q);

      $this->rewardLoyalty();

    } elseif ($this->paid) {
      // we thought we were paid, but now we must not be
      $q= "UPDATE txn SET paid = NULL WHERE id = {$this->id}";
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
    $q= "DELETE FROM payment WHERE id = $payment AND txn = {$this->id}";
    $r= $this->db->query($q)
      or die_query($this->db, $q);

    if ($this->paid) {
      $q= "UPDATE txn SET paid = NULL WHERE id = {$this->id}";
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
    if (!defined('LOYALTY')) return;

    // No person? No loyalty.
    if (!$this->person)
      return;

    // Use rewards
    $q= "INSERT INTO loyalty (txn_id, person_id, processed, note, points)
         SELECT {$this->id} txn_id,
                {$this->person} person_id,
                NOW() processed,
                name note,
                cost * allocated points
           FROM loyalty_reward
           JOIN txn_line ON loyalty_reward.item_id = txn_line.item
           JOIN item ON txn_line.item = item.id
          WHERE txn = {$this->id}";
    // XXX throw an exception on failure
    $r= $this->db->query($q)
        or die_query($this->db, $q);

    // No rewards for this txn?
    if ($this->no_rewards)
      return;

    // Award new points
    $points= (int)$this->subtotal;
    if ($points == 0 && $this->subtotal > 0) $points= 1;

    $q= "INSERT INTO loyalty
            SET txn_id= {$this->id},
                person_id = {$this->person},
                processed = NOW(),
                note = 'Pt Earned',
                points = $points";
    // XXX throw an exception on failure
    $r= $this->db->query($q)
        or die_query($this->db, $q);
  }

  public function clearLoyalty() {
    if (!defined('LOYALTY')) return;

    $q= "DELETE FROM loyalty
          WHERE txn_id = {$this->id}";
    // XXX throw an exception on failure
    $r= $this->db->query($q)
        or die_query($this->db, $q);
  }
}
