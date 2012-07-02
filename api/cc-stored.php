<?
include '../scat.php';
include '../lib/txn.php';

bcscale(2);

$id= (int)$_REQUEST['id'];
$amount= $_REQUEST['amount'];

if (!$id || !$amount)
  die_jsonp("Either transaction or amount was not specified.");

$person_id= (int)$_REQUEST['person'];
$person= $person_id ? person_load($db, $person_id) : false;

if (!$person_id || !$person || !$person['payment_account_id'])
  die_jsonp("No person specified or no card stored for person.");

$request= <<<XML
<?xml version="1.0" standalone="yes"?>
<CreditCardSale xmlns="https://transaction.elementexpress.com">
 <Application>
  <ApplicationID>{EPS_ApplicationID}</ApplicationID>
  <ApplicationName>{APP_NAME}</ApplicationName>
  <ApplicationVersion>{VERSION}</ApplicationVersion>
 </Application>
 <Credentials>
  <AccountID>{EPS_AccountID}</AccountID>
  <AccountToken>{EPS_AccountToken}</AccountToken>
  <AcceptorID>{EPS_AcceptorID}</AcceptorID>
 </Credentials>
 <Terminal>
  <TerminalID>1</TerminalID>
  <TerminalType>1</TerminalType><!-- PointOfSale -->
  <TerminalEnvironmentCode>2</TerminalEnvironmentCode><!-- LocalAttended -->
  <CardholderPresentCode>2</CardholderPresentCode><!-- Present -->
  <CardPresentCode>2</CardPresentCode><!-- Present -->
  <CVVPresenceCode>0</CVVPresenceCode><!-- Presence -->
  <TerminalCapabilityCode>5</TerminalCapabilityCode><!-- MagstripReader -->
  <CardInputCode>0</CardInputCode><!-- Default -->
  <MotoECICode>1</MotoECICode><!-- NotUsed -->
 </Terminal>
 <Transaction>
  <TransactionAmount>$amount</TransactionAmount>
  <ReferenceNumber>$id</ReferenceNumber>
 </Transaction>
 <PaymentAccount>
  <PaymentAccountID>{$person['payment_account_id']}</PaymentAccountID>
 </PaymentAccount>
</CreditCardSale>
XML;
$request= preg_replace("/<!-- .+ -->/", "", $request);
$request= preg_replace("/{(.+?)}/e", "constant('$1')", $request);

$ch= curl_init();
curl_setopt($ch, CURLOPT_URL, "https://certtransaction.elementexpress.com/");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response= curl_exec($ch);
curl_close($ch);

$xml= new SimpleXMLElement($response);

if ($xml->Response->ExpressResponseCode != 0) {
  die_jsonp((string)$xml->Response->ExpressResponseMessage);
}

$cc_txn= $db->escape($xml->Response->Transaction->TransactionID);
$cc_approval= $db->escape($xml->Response->Transaction->ApprovalNumber);
$cc_type= $db->escape($xml->Response->Card->CardLogo);

$method= 'credit';

$cc[]= "cc_txn = '$cc_txn', ";
$cc[]= "cc_approval = '$cc_approval', ";
$cc[]= "cc_type= '$cc_type', ";
$extra_fields= join('', $cc);

// add payment record
$q= "INSERT INTO payment
        SET txn = $id, method = '$method', amount = $amount,
        $extra_fields
        processed = NOW()";
$r= $db->query($q)
  or die_query($db, $q);

$payment= $db->insert_id;

$txn= txn_load($db, $id);

// if we're all paid up, record that the txn is paid
if (!bccomp($txn['total_paid'], $txn['total'])) {
  $q= "UPDATE txn SET paid = NOW() WHERE id = $id";
  $r= $db->query($q)
    or die_query($db, $q);
}

echo jsonp(array('payment' => $payment,
                 'txn' => txn_load($db, $id),
                 'payments' => txn_load_payments($db, $id)));
