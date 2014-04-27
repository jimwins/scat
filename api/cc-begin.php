<?
include '../scat.php';
include '../lib/txn.php';
include '../lib/eps-express.php';

$id= (int)$_REQUEST['id'];
$amount= $_REQUEST['amount'];
$partial= (int)$_REQUEST['partial'];

if (!$id || !$amount)
  die_jsonp("Either transaction or amount was not specified.");

$txn= new Transaction($db, $id);
if (!$txn->canPay('credit', $amount))
  die_jsonp("Amount is too much.");

$ReturnURL= ($_SERVER['HTTPS'] ? "https://" : "http://") .
            $_SERVER['HTTP_HOST'] .
            dirname($_SERVER['REQUEST_URI']) .
            '/cc-paid.php';

$eps= new EPS_Express();
$response= $eps->CreditCardSaleHosted($id, $amount, $partial, $ReturnURL);

$xml= new SimpleXMLElement($response);

$payment= $db->escape($xml->Response->Transaction->TransactionSetupID);
$valid= $db->escape($xml->Response->TransactionSetup->ValidationCode);
$q= "INSERT INTO hostedpayment_txn
        SET txn = $id,
            hostedpayment = '$payment',
            validationcode = '$valid',
            created = NOW()";
$db->query($q)
  or die_query($db, $q);

$url= "https://certtransaction.hostedpayments.com/?TransactionSetupID=" .
      $xml->Response->Transaction->TransactionSetupID;

$dom= dom_import_simplexml($xml);
$dom->ownerDocument->preserveWhiteSpace= false;
$dom->ownerDocument->formatOutput= true;

echo jsonp(array('url' => $url, 'xml' => $dom->ownerDocument->saveXML()));
