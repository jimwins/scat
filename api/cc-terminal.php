<?
include '../scat.php';
include '../lib/txn.php';
include '../lib/cc-terminal.php';

$id= (int)$_REQUEST['id'];
$type= $_REQUEST['type'];
$amount= $_REQUEST['amount'];

if (!in_array($type, array('Sale','Return'))) {
  die_jsonp("Requested type not understood.");
}

$txn= new Transaction($db, $id);

$cc= new CC_Terminal();

try {
  list($amount, $extra)= $cc->transaction($type, $amount,
                                          $txn->formatted_number);

  /* Reverse the sign on the amount if it was a return! */
  if ($type == 'Return') {
    $amount= bcmul($amount, -1);
  }

  $payment= $txn->addPayment('credit', $amount, $extra);

  $q= "INSERT INTO cc_trace (request, response, info)
            VALUES ('" . $db->escape($cc->raw_request) . "',
                    '" . $db->escape($cc->raw_response) . "',
                    '" . $db->escape($cc->raw_curlinfo) . "')";
  $db->query($q) or die_query($db, $q);

} catch (Exception $e) {

  $q= "INSERT INTO cc_trace (request, response, info)
            VALUES ('" . $db->escape($cc->raw_request) . "',
                    '" . $db->escape($cc->raw_response) . "',
                    '" . $db->escape($cc->raw_curlinfo) . "')";
  $db->query($q) or die_query($db, $q);

  die_jsonp($e->getMessage());
}

echo jsonp(array('payment' => $payment,
                 'txn' => txn_load($db, $id),
                 'response' => $response,
                 'extdata' => $extdata,
                 'payments' => txn_load_payments($db, $id)));
