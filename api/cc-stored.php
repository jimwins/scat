<?
include '../scat.php';
include '../lib/txn.php';
include '../lib/eps-express.php';

$id= (int)$_REQUEST['id'];
$amount= $_REQUEST['amount'];

if (!$id || !$amount)
  die_jsonp("Either transaction or amount was not specified.");

$person_id= (int)$_REQUEST['person'];
$person= $person_id ? person_load($db, $person_id) : false;
$account= $person['payment_account_id'];

if (!$person_id || !$person || !$account)
  die_jsonp("No person specified or no card stored for person.");

$eps= new EPS_Express();
$response= $eps->CreditCardSalePaymentAccount($id, $amount, $account);

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
  $payment= $txn->addPayment($method, $amount, $cc);
} catch (Exception $e) {
  die_jsonp($e->getMessage());
}

echo jsonp(array('payment' => $payment,
                 'txn' => txn_load($db, $id),
                 'payments' => txn_load_payments($db, $id)));
