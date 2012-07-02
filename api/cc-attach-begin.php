<?
include '../scat.php';

$person= (int)$_REQUEST['person'];

if (!$person)
  die_jsonp("Person was not specified.");

$ReturnURL= ($_SERVER['HTTPS'] ? "https://" : "http://") .
            $_SERVER['HTTP_HOST'] .
            dirname($_SERVER['REQUEST_URI']) .
            '/cc-attach-finish.php';

$request= <<<XML
<?xml version="1.0" standalone="yes"?>
<TransactionSetup xmlns="https://transaction.elementexpress.com">
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
 <TransactionSetup>
   <Embedded>0</Embedded>
   <TransactionSetupMethod>7</TransactionSetupMethod><!-- PaymentAccountCreate -->
   <CompanyName>Raw Materials</CompanyName>
   <ReturnURL>$ReturnURL</ReturnURL>
   <AutoReturn>1</AutoReturn>
 </TransactionSetup>
 <PaymentAccount>
  <PaymentAccountType>0</PaymentAccountType>
  <PaymentAccountReferenceNumber>$person</PaymentAccountReferenceNumber>
 </PaymentAccount>
</TransactionSetup>
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

$payment= $db->escape($xml->Response->Transaction->TransactionSetupID);
$valid= $db->escape($xml->Response->TransactionSetup->ValidationCode);
$q= "INSERT INTO hostedpayment_txn
        SET txn = $person,
            hostedpayment = '$payment',
            validationcode = '$valid',
            created = NOW()";
$db->query($q)
  or die_query($db, $q);

$url= "https://certtransaction.hostedpayments.com/?TransactionSetupID=" .
      $xml->Response->Transaction->TransactionSetupID;

$dom= dom_import_simplexml($xml);
$dom->ownerDocument->preserveWhiteSpace= false;
$dom->ownerDocumenut->formatOutput= true;

echo jsonp(array('url' => $url, 'xml' => $dom->ownerDocument->saveXML()));
