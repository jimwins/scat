<?
include '../scat.php';
include '../lib/txn.php';
include '../lib/eps-express.php';

$id= (int)$_REQUEST['txn'];
$payment= (int)$_REQUEST['payment'];

if (!$id)
  die_jsonp("Transaction not specified.");
if (!$payment)
  die_jsonp("Payment to reverse from not specified.");

$q= "SELECT cc_txn, amount FROM payment WHERE id = $payment";
list($cc_txn, $cc_amount)= $db->get_one_row($q)
  or die_jsonp("Unable to find transaction information.");

$eps= new EPS_Express();
$response= $eps->CreditCardVoid($id, $cc_txn);

$xml= new SimpleXMLElement($response);

if ($xml->Response->ExpressResponseCode != 0) {
  die_jsonp((string)$xml->Response->ExpressResponseMessage);
}

$method= 'credit';

$cc= array();
$cc['cc_txn']= $xml->Response->Transaction->TransactionID;
$cc['cc_approval']= $xml->Response->Transaction->ApprovalNumber;
$cc['cc_type']= $xml->Response->Card->CardLogo;

$txn= new Transaction($db, $id);

try {
  $payment= $txn->addPayment($method, bcmul($cc_amount, -1), $cc);
} catch (Exception $e) {
  die_jsonp($e->getMessage());
}

echo jsonp(array('payment' => $payment,
                 'txn' => txn_load($db, $id),
                 'payments' => txn_load_payments($db, $id)));
