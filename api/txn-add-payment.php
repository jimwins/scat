<?
include '../scat.php';
include '../lib/txn.php';

$id= (int)$_REQUEST['id'];

if (!$id)
  die_jsonp("No transaction specified.");

$method= $_REQUEST['method'];
$amount= $_REQUEST['amount'];

// validate method
if (!in_array($method,
              array('cash','credit','square','stripe','dwolla','gift','check','discount','bad','donation','internal'))) {
  die_jsonp("Invalid method specified.");
}

$txn= new Transaction($db, $id);

$extra= array(); // extra payment info

// handle % discounts
if ($method == 'discount') {
  if (preg_match('!^(/)?\s*(\d+)(%|/)?\s*$!', $amount, $m)) {
    if ($m[1] || $m[3]) {
      $amount= round($txn->total * $m[2] / 100,
                     2, PHP_ROUND_HALF_EVEN);
      $extra['discount']= $m[2];
    }
  }
}

if ($method == 'credit') {
  $cc= array();
  foreach(array('cc_txn', 'cc_approval', 'cc_lastfour',
                'cc_expire', 'cc_type') as $field) {
    $extra[$field]= $_REQUEST[$field];
  }
}

try {
  $payment= $txn->addPayment($method, $amount, $extra);
} catch (Exception $e) {
  die_jsonp($e->getMessage());
}

echo jsonp(array('payment' => $payment,
                 'txn' => txn_load($db, $id),
                 'payments' => txn_load_payments($db, $id)));
