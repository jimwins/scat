<?php
namespace Scat\Service;

class Scat
{
  public $url;

  public function __construct(Config $config) {
    $this->url= $config->get('scat.url');
  }

  protected function getClient() {
    return new \GuzzleHttp\Client([
      'timeout' => 2,
      'connect_timeout' => 2,
    ]);
  }

  public function find_person($loyalty) {
    $client= $this->getClient();

    $uri= $this->url . "/person/search/?loyalty=" . rawurlencode($loyalty);

    try {
      $res= $client->get($uri, [
        'headers' => [ 'Accept' => 'application/json' ]
      ]);
    } catch (\Exception $e) {
      throw $e;
    }

    $people= json_decode($res->getBody());

    if (json_last_error() != JSON_ERROR_NONE) {
      throw new \Exception(json_last_error_msg());
    }

    return $people;
  }

  public function sendSMS($to, $message) {
    $client= $this->getClient();

    $uri= $this->url . "/sms/~send";

    try {
      $res= $client->post($uri, [
        'json' => [
          'to' => $to,
          'text' => $message,
        ],
        'headers' => [ 'Accept' => 'application/json' ]
      ]);
    } catch (\Exception $e) {
      throw $e;
    }

    $data= json_decode($res->getBody());

    if (json_last_error() != JSON_ERROR_NONE) {
      throw new \Exception(json_last_error_msg());
    }
  }

  public function get_person_details($person_id) {
    $client= $this->getClient();

    $uri= $this->url . "/person/" . $person_id;

    try {
      $res= $client->get($uri, [
        'headers' => [ 'Accept' => 'application/json' ]
      ]);
    } catch (\Exception $e) {
      throw $e;
    }

    $data= json_decode($res->getBody());

    if (json_last_error() != JSON_ERROR_NONE) {
      throw new \Exception(json_last_error_msg());
    }

    return $data;
  }

  public function get_orders($person_id) {
    $client= $this->getClient();

    $uri= $this->url . "/person/" . $person_id . '/sale';

    try {
      $res= $client->get($uri, [
        'headers' => [ 'Accept' => 'application/json' ]
      ]);
    } catch (\Exception $e) {
      throw $e;
    }

    $data= json_decode($res->getBody());

    if (json_last_error() != JSON_ERROR_NONE) {
      throw new \Exception(json_last_error_msg());
    }

    return $data;
  }

  public function get_sale_invoice($uuid) {
    $client= $this->getClient();

    $uri= $this->url . "/sale/" . $uuid;

    try {
      $res= $client->get($uri, [
        'headers' => [ 'Accept' => 'application/json' ]
      ]);
    } catch (\Exception $e) {
      throw $e;
    }

    $data= json_decode($res->getBody());

    if (json_last_error() != JSON_ERROR_NONE) {
      throw new \Exception(json_last_error_msg());
    }

    $uri= $this->url . "/sale/" . $data->id . '/~print-invoice?download=1';

    return $client->post($uri);
  }

  public function get_giftcard_balance($card) {
    $client= $this->getClient();

    $uri= $this->url . "/gift-card/" . rawurlencode($card);

    try {
      $res= $client->get($uri, [
        'headers' => [ 'Accept' => 'application/json' ]
      ]);
    } catch (\Exception $e) {
      throw $e;
    }

    $data= json_decode($res->getBody());

    if (json_last_error() != JSON_ERROR_NONE) {
      throw new \Exception(json_last_error_msg());
    }

    return $data->balance;
  }

  public function track_shipment($uuid, $shipment_id) {
    $client= $this->getClient();

    $uri= $this->url . "/sale/" . $uuid;

    try {
      $res= $client->get($uri, [
        'headers' => [ 'Accept' => 'application/json' ]
      ]);
    } catch (\Exception $e) {
      throw $e;
    }

    $data= json_decode($res->getBody());

    if (json_last_error() != JSON_ERROR_NONE) {
      throw new \Exception(json_last_error_msg());
    }

    $uri= $this->url . "/shipment/" . $shipment_id . '/track';

    return $client->get($uri);
  }
}
