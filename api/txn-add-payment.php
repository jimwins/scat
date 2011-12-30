<?
include '../scat.php';

bcscale(2);

$id= (int)$_REQUEST['id'];

if (!$id)
  die_jsonp("No transaction specified.");

$method= $_REQUEST['method'];
$amount= $_REQUEST['amount'];

// validate method
if (!in_array($method,
              array('cash','credit','gift','check','discount'))) {
  die_jsonp("Invalid method specified.");
}

// load transaction
$q= "SELECT id, type,
            number, created, filled, paid,
            CONCAT(DATE_FORMAT(created, '%Y-'), number) AS formatted_number,
            person, person_name,
            IFNULL(ordered, 0) ordered, allocated,
            taxed, untaxed, tax_rate,
            CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                 AS DECIMAL(9,2)) total,
            IFNULL(total_paid, 0.00) total_paid
      FROM (SELECT
            txn.id, txn.type, txn.number,
            txn.created, txn.filled, txn.paid,
            txn.person,
            CONCAT(IFNULL(person.name, ''),
                   IF(person.name AND person.company, ' / ', ''),
                   IFNULL(person.company, ''))
                AS person_name,
            SUM(ordered) * IF(txn.type = 'customer', -1, 1) AS ordered,
            SUM(allocated) * IF(txn.type = 'customer', -1, 1) AS allocated,
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
                 AS DECIMAL(9,2)) AS total_paid
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
       LEFT JOIN person ON (txn.person = person.id)
      WHERE txn.id = $id) t";

$r= $db->query($q)
  or die_query($db, $q);

$txn= $r->fetch_assoc();
$txn['total_paid']= (float)$txn['total_paid'];

// if set, allow overpayment and create a 'change' record
$change= (bool)$_REQUEST['change'];

// if no change and amount + paid > total, barf
if (!$change && bccomp(bcadd($amount, $txn['total_paid']), $txn['total']) > 0) {
  die_jsonp("Amount is too much.");
}

// add payment record
$q= "INSERT INTO payment
        SET txn = $id, method = '$method', amount = $amount,
        processed = NOW()";
// XX handle cc fields
$r= $db->query($q)
  or die_query($db, $q);

// if amount + paid > total, add change record
$change_paid= 0.0;
if (bccomp(bcadd($amount, $txn['total_paid']), $txn['total']) > 0) {
  $change_paid= $txn['total'] - ($amount + $txn['total_paid']);

  $q= "INSERT INTO payment
          SET txn = $id, method = 'change', amount = $change_paid,
          processed = NOW()";
  $r= $db->query($q)
    or die_query($db, $q);
}

$txn['total_paid'] = bcadd($txn['total_paid'], bcadd($amount, $change_paid));

// if we're all paid up, record that the txn is paid
if (!bccomp($txn['total_paid'], $txn['total'])) {
  $q= "UPDATE txn SET paid = NOW() WHERE id = $id";
  $r= $db->query($q)
    or die_query($db, $q);
}

// generate response including list of payments and header info
$q= "SELECT id, processed, method, amount
       FROM payment
      WHERE txn = $id
      ORDER BY processed ASC";

$r= $db->query($q)
  or die_query($db, $q);

$payments= array();
while ($row= $r->fetch_assoc()) {
  /* force numeric values to numeric type */
  $row['amount']= (float)$row['amount'];
  $payments[]= $row;
}

echo generate_jsonp(array('txn' => $txn, 'payments' => $payments));
