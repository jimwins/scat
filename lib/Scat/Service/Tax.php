<?php
namespace Scat\Service;

class Tax
{
  private $apiLoginID;
  private $apiKey;

  public function __construct(Config $config) {
    $this->apiLoginID= $config->get('taxcloud.id');
    $this->apiKey= $config->get('taxcloud.key');
  }

  /* Very simple wrapper that builds the URL, merges in the API credentials,
   * and turns errors into exceptions. */
  protected function callApi($method, $params= []) {
    $client= new \GuzzleHttp\Client();

    $uri= 'https://api.taxcloud.net/1.0/TaxCloud/' . $method;
    $cred= [ 'apiKey' => $this->apiKey, 'apiLoginID' => $this->apiLoginID ];

    $response= $client->post($uri, [ 'json' => array_merge($cred, $params) ]);

    $data= json_decode($response->getBody());

    if (json_last_error() != JSON_ERROR_NONE) {
      throw new \Exception(json_last_error_msg());
    }

    if (property_exists($data, 'ErrNumber') && $data->ErrNumber != "0") {
      throw new \Exception($data->ErrDescription);
    }

    return $data;
  }

  public function ping() {
    return $this->callApi('Ping');
  }

  public function addTransactions($transactions) {
    return $this->callApi('AddTransactions', [
      'transactions' => $transactions
    ]);
  }
}
