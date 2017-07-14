<?
include '../scat.php';
include '../lib/txn.php';

include_encrypted('../lib/cc-terminal.phpc');

$id= (int)$_REQUEST['id'];
$payment_id= (int)$_REQUEST['payment_id'];

$txn= new Transaction($db, $id);

if (!$txn->id) {
  die_jsonp("Unable to find transaction.");
}

$q= "SELECT cc_refid FROM payment WHERE id = $payment_id";
$refid= $db->get_one($q);

if (!$refid) {
  die_jsonp("Not able to void this payment.");
}

$q= "SELECT amount FROM payment WHERE id = $payment_id";
$amount= $db->get_one($q);

$cc= new CC_Terminal();

try {
  list($amount, $extra)= $cc->void($refid, $amount, $txn->formatted_number);

  /* Reverse the sign on the amount (it's like a return) */
  $amount= bcmul($amount, -1);

  $payment= $txn->addPayment('credit', $amount, $extra);

  $q= "INSERT INTO cc_trace (txn_id, payment_id, request, response, info)
            VALUES ($id, $payment,
                    '" . $db->escape($cc->raw_request) . "',
                    '" . $db->escape($cc->raw_response) . "',
                    '" . $db->escape($cc->raw_curlinfo) . "')";
  $db->query($q) or die_query($db, $q);

} catch (Exception $e) {

  $q= "INSERT INTO cc_trace (txn_id, request, response, info)
            VALUES ($id,
                    '" . $db->escape($cc->raw_request) . "',
                    '" . $db->escape($cc->raw_response) . "',
                    '" . $db->escape($cc->raw_curlinfo) . "')";
  $db->query($q) or die_query($db, $q);

  die_jsonp($e->getMessage());
}

echo jsonp(txn_load_full($db, $id));
