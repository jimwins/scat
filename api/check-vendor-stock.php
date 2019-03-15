<?php
include '../scat.php';

$vendor= $_REQUEST['vendor'];
$code= $_REQUEST['code'];

switch ($vendor) {
case 7: // Mac
  echo jsonp(array('status' => check_mac_stock($code)));
  break;
case 50: // C2F
  echo jsonp(array('status' => check_c2f_stock($code)));
  break;
case 3757: // SLS
  echo jsonp(array('status' => check_sls_stock($code)));
  break;
default:
  die_json("Don't know how to check stock for that vendor.");
}

function check_mac_stock($code) {
  $url= 'https://www.macphersonart.com/cgi-bin/maclive/wam_tmpl/mac_cart.p';

  $client= new \GuzzleHttp\Client();
  $client->setUserAgent(APP_NAME . '/' . VERSION, true);
  $jar= \GuzzleHttp\Cookie\CookieJar::fromArray(['liveWAMSession' => MAC_KEY],
                                                parse_url($url, PHP_URL_HOST));

  $res= $client->request('GET', $url,
                         [
                         //'debug' => true,
                           'cookies' => $jar,
                           'query' => [
                             'site' => 'MAC',
                             'layout' => 'Responsive',
                             'nocache' => 45583,
                             'content' => 'JSON',
                             'page' => 'mac_cart',
                             'action' => 'getItemInfo',
                             'itemNumber' => $code
                           ]
                         ]);

  $body= $res->getBody();

  $data= json_decode($body);
  $avail= [];

  foreach ($data->response->itemInfo as $item) {
    foreach ($item->itemQty as $qty) {
      $avail[$qty->warehouseName]= $qty->qty;
    }
  }

  return $avail;
}

function check_sls_stock($code) {
  $url= 'https://www.slsarts.com/viewcarttop.asp';

  $client= new \GuzzleHttp\Client();
  $client->setUserAgent(APP_NAME . '/' . VERSION, true);
  $jar= \GuzzleHttp\Cookie\CookieJar::fromArray(['ASPSESSIONIDAETTQDRC'
                                                   => SLS_KEY],
                                                parse_url($url, PHP_URL_HOST));

  $res= $client->request('POST', $url,
                         [
                         //'debug' => true,
                           'cookies' => $jar,
                           'form_params' => [
                             'defwh' => 2,
                             'imaacr' => '',
                             'forcebottom' => 'N',
                             'slssku' => $code,
                             'slsskuorig' => '',
                             'slsqty' => '',
                             'WH' => 2,
                             'oldwh' => '',
                             'goclear' => '',
                             'vmwvnm' => '',
                             'imadsc' => '',
                             'msrp' => '',
                             'minqty' => '',
                             'qoh1' => '',
                             'price' => '',
                             'disc' => 0,
                             'qoh2' => '',
                             'borderitem' => 'Order Item',
                             'verror' => '',
                             'vonblur' => 'Y',
                             'vedititem' => '',
                             'veditwh' => '',
                             'vdelitem' => '',
                             'vdropship' => 'E',
                             'deletelist' => '',
                             'thepos' => '',
                           ]
                         ]);

  $body= $res->getBody();

  $dom= new DOMDocument();
  libxml_use_internal_errors(true);
  $dom->loadHTML($body);

  $xp= new DOMXpath($dom);
  $no= $xp->query('//input[@name="qoh1"]')->item(0)->getAttribute('value');
  $vg= $xp->query('//input[@name="qoh2"]')->item(0)->getAttribute('value');

  return [ 'Vegas' => $vg, 'New Orleans' => $no ];
}

function check_c2f_stock($code) {
  $url= 'https://www.c2f.com/exhtml/productdetail.asp';

  $client= new \GuzzleHttp\Client();
  $client->setUserAgent(APP_NAME . '/' . VERSION, true);
  $jar= \GuzzleHttp\Cookie\CookieJar::fromArray(['ASPSESSIONIDSEBSDQTA'
                                                   => C2F_KEY,
                                                 'C2FDealerID'
                                                   => C2F_ID ],
                                                parse_url($url, PHP_URL_HOST));

  $res= $client->request('GET', $url,
                         [
                         //'debug' => true,
                           'cookies' => $jar,
                           'query' => [
                             'p' => $code,
                           ]
                         ]);

  $body= $res->getBody();

  $dom= new DOMDocument();
  libxml_use_internal_errors(true);
  $dom->loadHTML($body);

  $xp= new DOMXpath($dom);
  $bv= $xp->query('//div[@class="stock"]/strong')->item(0)->textContent;

  return [ 'Beaverton' => $bv ];
}
