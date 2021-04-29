<?php
namespace Scat\Service;

class Tax
{
  private $apiLoginID;
  private $apiKey;
  private $config;
  public $default_rate;

  public function __construct(Config $config) {
    $this->apiLoginID= $config->get('taxcloud.id');
    $this->apiKey= $config->get('taxcloud.key');
    $this->default_rate= $config->get('tax.default_rate');
  }

  /* Very simple wrapper that builds the URL, merges in the API credentials,
   * and turns errors into exceptions. */
  protected function callApi($method, $params= []) {
    $client= new \GuzzleHttp\Client();

    $uri= 'https://api.taxcloud.net/1.0/TaxCloud/' . $method;
    $cred= [ 'apiKey' => $this->apiKey, 'apiLoginID' => $this->apiLoginID ];

    $response= $client->post($uri, [ 'json' => array_merge($cred, $params) ]);

    $body= $response->getBody();
    $data= json_decode($body);

    if (json_last_error() != JSON_ERROR_NONE) {
      throw new \Exception(json_last_error_msg());
    }

    if (property_exists($data, 'ErrNumber') && $data->ErrNumber != "0") {
      file_put_contents("/tmp/taxcloud-in.json", json_encode($params));
      file_put_contents("/tmp/taxcloud-out.json", $body);
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

  public function getTICs() {
    return $this->callApi('GetTICs');
  }

  public function lookup($data) {
    return $this->callApi('Lookup', $data);
  }

  public function authorizedWithCapture($data) {
    return $this->callApi('AuthorizedWithCapture', $data);
  }

  public function returned($data) {
    return $this->callApi('Returned', $data);
  }

  public function addExemptCertificate($data) {
    return $this->callApi('AddExemptCertificate', $data);
  }

  public function getExemptCertificates($data) {
    return $this->callApi('GetExemptCertificates', $data);
  }

  public function deleteExemptCertificate($data) {
    return $this->callApi('DeleteExemptCertificate', $data);
  }

}
