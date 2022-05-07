<?php
namespace Scat\Service;

class Scat
{
  public $url;
  public $key;

  public function __construct(Config $config) {
    $this->url= $config->get('scat.url');
    $this->key= $config->get('scat.key');
  }

  public function find_person($loyalty) {
    $client= new \GuzzleHttp\Client();

    $uri= $this->url . "/person/search/?loyalty=" . $loyalty;

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
    $client= new \GuzzleHttp\Client();

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
    $client= new \GuzzleHttp\Client();

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
}
