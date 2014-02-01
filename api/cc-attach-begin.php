<?
include '../scat.php';
include '../lib/eps-express.php';

$person= (int)$_REQUEST['person'];
$payment_account_id= $_REQUEST['payment_account_id'];

if (!$person)
  die_jsonp("Person was not specified.");

$ReturnURL= ($_SERVER['HTTPS'] ? "https://" : "http://") .
            $_SERVER['HTTP_HOST'] .
            dirname($_SERVER['REQUEST_URI']) .
            '/cc-attach-finish.php';

$eps= new EPS_Express();

if ($payment_account_id) {
  $response= $eps->PaymentAccountUpdateHosted($person, $payment_account_id,
                                              $ReturnURL);
} else {
  $response= $eps->PaymentAccountCreateHosted($person, $ReturnURL);
}

$payment= $db->escape($response->Transaction->TransactionSetupID);
$valid= $db->escape($response->TransactionSetup->ValidationCode);
$q= "INSERT INTO hostedpayment_txn
        SET txn = $person,
            hostedpayment = '$payment',
            validationcode = '$valid',
            created = NOW()";
$db->query($q)
  or die_query($db, $q);

$url= "https://certtransaction.hostedpayments.com/?TransactionSetupID=" .
      $response->Transaction->TransactionSetupID;

echo jsonp(array('url' => $url, 'response' => $response));
