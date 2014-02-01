<?php

class EPS_Express {
  private function request($request) {
    // remove comments
    $request= preg_replace("/<!-- .+ -->/", "", $request);
    // replace constants
    $request= preg_replace("/{(.+?)}/e", "constant('$1')", $request);

    $ch= curl_init();
    curl_setopt($ch, CURLOPT_URL, constant('EPS_URL'));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response= curl_exec($ch);
    curl_close($ch);

    $xml= new SimpleXMLElement($response);

    return $xml->Response;
  }

  public function CreditCardReturn($id, $cc_txn, $cc_amount) {
    $request= <<<XML
<?xml version="1.0" standalone="yes"?>
<CreditCardReturn xmlns="https://transaction.elementexpress.com">
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
  <TerminalID>0001</TerminalID>
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
  <TransactionID>$cc_txn</TransactionID>
  <TransactionAmount>$cc_amount</TransactionAmount>
  <ReferenceNumber>$id</ReferenceNumber>
 </Transaction>
</CreditCardReturn>
XML;
    $request= preg_replace("/<!-- .+ -->/", "", $request);
    $request= preg_replace("/{(.+?)}/e", "constant('$1')", $request);

    $ch= curl_init();
    curl_setopt($ch, CURLOPT_URL, EPS_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response= curl_exec($ch);
    curl_close($ch);

    return $response;
  }

  public function CreditCardReversal($id, $cc_txn, $cc_amount) {
    $request= <<<XML
<?xml version="1.0" standalone="yes"?>
<CreditCardReversal xmlns="https://transaction.elementexpress.com">
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
  <TerminalID>0001</TerminalID>
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
  <ReversalType>1</ReversalType><!-- Full -->
  <TransactionID>$cc_txn</TransactionID>
  <TransactionAmount>$cc_amount</TransactionAmount>
  <ReferenceNumber>$id</ReferenceNumber>
 </Transaction>
</CreditCardReversal>
XML;
    $request= preg_replace("/<!-- .+ -->/", "", $request);
    $request= preg_replace("/{(.+?)}/e", "constant('$1')", $request);

    $ch= curl_init();
    curl_setopt($ch, CURLOPT_URL, EPS_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response= curl_exec($ch);
    curl_close($ch);

    return $response;
  }

  public function CreditCardSaleHosted($id, $amount, $partial, $ReturnURL) {
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
  <TerminalID>0001</TerminalID>
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
   <TransactionSetupMethod>1</TransactionSetupMethod><!-- CreditCardSale -->
   <CompanyName>Raw Materials Art Supplies</CompanyName>
   <ReturnURL>{$ReturnURL}</ReturnURL>
   <AutoReturn>1</AutoReturn>
 </TransactionSetup>
 <Transaction>
  <TransactionAmount>{$amount}</TransactionAmount>
  <ReferenceNumber>{$id}</ReferenceNumber>
  <PartialApprovedFlag>{$partial}</PartialApprovedFlag>
 </Transaction>
</TransactionSetup>
XML;
    $request= preg_replace("/<!-- .+ -->/", "", $request);
    $request= preg_replace("/{(.+?)}/e", "constant('$1')", $request);

    $ch= curl_init();
    curl_setopt($ch, CURLOPT_URL, EPS_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response= curl_exec($ch);
    curl_close($ch);

    return $response;
  }

  public function CreditCardSalePaymentAccount($id, $amount, $account_id) {
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
  <TerminalID>0001</TerminalID>
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
  <TransactionAmount>{$amount}</TransactionAmount>
  <ReferenceNumber>{$id}</ReferenceNumber>
 </Transaction>
 <PaymentAccount>
  <PaymentAccountID>{$account_id}</PaymentAccountID>
 </PaymentAccount>
</CreditCardSale>
XML;
    $request= preg_replace("/<!-- .+ -->/", "", $request);
    $request= preg_replace("/{(.+?)}/e", "constant('$1')", $request);

    $ch= curl_init();
    curl_setopt($ch, CURLOPT_URL, EPS_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response= curl_exec($ch);
    curl_close($ch);

    return $response;
  }

  public function CreditCardVoid($id, $cc_txn) {
    $request= <<<XML
<?xml version="1.0" standalone="yes"?>
<CreditCardVoid xmlns="https://transaction.elementexpress.com">
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
  <TerminalID>0001</TerminalID>
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
  <TransactionID>$cc_txn</TransactionID>
  <ReferenceNumber>$id</ReferenceNumber>
 </Transaction>
</CreditCardVoid>
XML;
    $request= preg_replace("/<!-- .+ -->/", "", $request);
    $request= preg_replace("/{(.+?)}/e", "constant('$1')", $request);

    $ch= curl_init();
    curl_setopt($ch, CURLOPT_URL, EPS_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response= curl_exec($ch);
    curl_close($ch);

    return $response;
  }


  public function PaymentAccountCreateHosted($person, $ReturnURL) {
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
  <TerminalID>0001</TerminalID>
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
   <CompanyName>Raw Materials Art Supplies</CompanyName>
   <ReturnURL>{$ReturnURL}</ReturnURL>
   <AutoReturn>1</AutoReturn>
 </TransactionSetup>
 <PaymentAccount>
  <PaymentAccountType>0</PaymentAccountType>
  <PaymentAccountReferenceNumber>{$person}</PaymentAccountReferenceNumber>
 </PaymentAccount>
</TransactionSetup>
XML;

    return $this->request($request);
  }

  public function PaymentAccountDelete($person) {
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
  <PaymentAccountID>{$person}</PaymentAccountID>
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

    return $response;
  }

  public function PaymentAccountUpdateHosted($person, $payment_account_id,
                                             $ReturnURL) {
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
  <TerminalID>0001</TerminalID>
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
   <CompanyName>Raw Materials Art Supplies</CompanyName>
   <ReturnURL>{$ReturnURL}</ReturnURL>
   <AutoReturn>1</AutoReturn>
 </TransactionSetup>
 <PaymentAccount>
  <PaymentAccountType>0</PaymentAccountType><!-- CreditCard -->
  <PaymentAccountID>{$payment_account_id}</PaymentAccountID>
  <PaymentAccountReferenceNumber>{$person}</PaymentAccountReferenceNumber>
 </PaymentAccount>
</TransactionSetup>
XML;

    return $this->request($request);
  }
}
