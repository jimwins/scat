<?
include '../scat.php';
include '../lib/txn.php';

$person_id= (int)$_REQUEST['person'];
$person= $person_id ? person_load($db, $person_id) : false;

if (!$person_id || !$person || !$person['payment_account_id'])
  die_jsonp("No person specified or no card stored for person.");

$request= <<<XML
<?xml version="1.0" standalone="yes"?>
<PaymentAccountDelete xmlns="https://services.elementexpress.com">
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
 <PaymentAccount>
  <PaymentAccountID>{$person['payment_account_id']}</PaymentAccountID>
 </PaymentAccount>
</PaymentAccountDelete>
XML;
$request= preg_replace("/<!-- .+ -->/", "", $request);
$request= preg_replace("/{(.+?)}/e", "constant('$1')", $request);

$ch= curl_init();
curl_setopt($ch, CURLOPT_URL, "https://certservices.elementexpress.com/");
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

// remove payment account info from person
$q= "UPDATE person
        SET payment_account_id = NULL
      WHERE id = $person_id";
$r= $db->query($q)
  or die_query($db, $q);

echo jsonp(array('person' => person_load($db, $person_id)));
