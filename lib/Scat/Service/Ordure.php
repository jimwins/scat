<?php
namespace Scat\Service;

class Ordure
{
  public $url;
  public $key;
  public $static_url;

  public function __construct(Config $config) {
    $this->url= $config->get('ordure.url');
    $this->key= $config->get('ordure.key');
    $this->static_url= $config->get('ordure.static_url');
  }

  public function markOrderShipped($sale_uuid) {
    $url= $this->url . '/sale/' . $sale_uuid . '/set-status';

    $client= new \GuzzleHttp\Client();
    $res= $client->request('POST', $url,
                           [
                             'headers' => [
                               'X-Requested-With' => 'XMLHttpRequest',
                             ],
                             'form_params' => [
                               'key' => $this->key,
                               'status' => 'shipped'
                             ]
                           ]);
    // XXX do something with $res?
  }

  public function grabImage($url) {
    $client= new \GuzzleHttp\Client();
    $res= $client->request('POST', $this->url . '/~grab-image',
                           [
                             'headers' => [
                               'X-Requested-With' => 'XMLHttpRequest',
                             ],
                             'form_params' => [
                               'key' => $this->key,
                               'url' => $url,
                             ]
                           ]);

    return json_decode($res->getBody());
  }
}
