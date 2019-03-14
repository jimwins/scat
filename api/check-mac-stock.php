<?php
include '../scat.php';

$code= $_REQUEST['code'];

echo jsonp(array('status' => check_mac_stock($code)));

function check_mac_stock($code) {
  $url= 'https://www.macphersonart.com/cgi-bin/maclive/wam_tmpl/mac_cart.p';

  $client= new \GuzzleHttp\Client();
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
