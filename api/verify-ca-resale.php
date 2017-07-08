<?php

include '../scat.php';

$number= $_REQUEST['number'];

echo jsonp(array('status' => verify_ca_resale($number)));

function verify_ca_resale($number) {
  $number= preg_replace('/[^\d]/', '', $number);

  $client= new \GuzzleHttp\Client();
  $jar= new \GuzzleHttp\Cookie\CookieJar();

  $url= 'https://efile.boe.ca.gov/boewebservices/servlet/BOEVerification';

  $res= $client->request('POST', $url,
                         [
                           // 'debug' => true,
                           'cookies' => $jar,
                           'form_params' => [ 'account' => $number ]
                         ]);

  $body= $res->getBody();

  if (preg_match('!Number \d+ is <b><span style=".+?">(.+)</span>!',
                 $body, $m)) {
    return $m[1];
  }

  return "Undetermined";
}
