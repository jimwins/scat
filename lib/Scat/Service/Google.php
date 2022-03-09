<?php
namespace Scat\Service;

class Google
{
  private $service_key, $merchant_center_id;

  public function __construct(Config $config) {
    $this->service_key= $config->get('google.service_key');
    $this->merchant_center_id= $config->get('google.merchant_center_id');

    if ($this->service_key) {
      $this->service_key= json_decode($this->service_key, true);
    }
  }

  public function getClient() {
    $client= new \Google\Client();
    $client->setApplicationName('Scat POS');
    $client->setAuthConfig($this->service_key);
    $client->setAccessType('offline');
    $client->setIncludeGrantedScopes(true);
    $client->setScopes('https://www.googleapis.com/auth/content');

    return $client;
  }

  function getItemShoppingHistory($code) {
    $client= $this->getClient();

    $service= new \Google\Service\ShoppingContent($client);

    $code= addslashes(strtolower($code));
    $query= "SELECT segments.date, segments.program,
                    metrics.impressions, metrics.clicks
               FROM MerchantPerformanceView
              WHERE segments.date DURING LAST_30_DAYS
                AND segments.offer_id = '$code'";

    $req= new \Google\Service\ShoppingContent\SearchRequest();
    $req->setQuery($query);

    return $service->reports->search(17327991, $req)->getResults();
  }

}
