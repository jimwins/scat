<?
include '../scat.php';
include '../lib/txn.php';

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

$txn= txn_load($db, $id);

// if set, allow overpayment and create a 'change' record
$change= (bool)($_REQUEST['change'] != 'false');

// if no change and amount + paid > total, barf
if (!$change && bccomp(bcadd($amount, $txn['total_paid']), $txn['total']) > 0) {
  die_jsonp("Amount is too much.");
}

$cc_fields= "";

if ($method == 'credit') {
  $cc= array();
  foreach(array('cc_txn', 'cc_approval', 'cc_lastfour',
                'cc_expire', 'cc_type') as $field) {
    $cc[]= "$field = '" . $db->real_escape_string($_REQUEST[$field]) . "', ";
  }

  $cc_fields= join('', $cc);
}

// add payment record
$q= "INSERT INTO payment
        SET txn = $id, method = '$method', amount = $amount,
        $cc_fields
        processed = NOW()";
$r= $db->query($q)
  or die_query($db, $q);

$payment= $db->insert_id;

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

$txn= txn_load($db, $id);

echo generate_jsonp(array('payment' => $payment,
                          'txn' => $txn,
                          'payments' => $payments));
