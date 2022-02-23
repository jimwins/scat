<?php
namespace Scat\Service;

class Google
{
  private $client_id;
  private $client_secret;
  private $token;

  public function __construct(Config $config) {
    $this->client_id= $config->get('google.client_id');
    $this->client_secret= $config->get('google.client_secret');
    $this->token= $config->get('google.oauth_access_token');

    if ($this->token) {
      $this->token= json_decode($this->token, true);
    }
  }

  public function getClient() {
    $client= new \Google\Client();
    $client->setApplicationName('Scat POS');
    $client->setClientId($this->client_id);
    $client->setClientSecret($this->client_secret);
    //$client->setRedirectUri($request->getUri()->withQuery(""));
    $client->setAccessType('offline');
    $client->setIncludeGrantedScopes(true);
    $client->setScopes('https://www.googleapis.com/auth/content');

    if ($this->token) {
      $client->setAccessToken($this->token);
    }

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
