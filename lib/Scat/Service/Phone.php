<?php
namespace Scat\Service;

class Phone
{
  private $token;
  private $from;
  private $account_id;
  private $webhook_url;

  public function __construct(array $config) {
    $this->token= $config['token'];
    $this->account_id= $config['account_id'];
    $this->from= $config['from'];
    $this->webhook_url= $config['webhook_url'];
  }

  public function sendSMS($to, $text) {
    $client= new \GuzzleHttp\Client();

    $url= "https://api.phone.com/v4/accounts/{$this->account_id}/sms";
    $data= [ 'from' => $this->from, 'to' => $to, 'text' => $text ];

    $res= $client->request('POST', $url, [
                             //'debug' => true,
                             'form_params' => $data,
                             'headers' => [
                               'authorization' => "Bearer {$this->token}",
                               'cache-control' => "no-cache",
                             ],
                           ]);

    $body= $res->getBody();

    return json_decode($body);
  }

  public function registerWebhook() {
    $client= new \GuzzleHttp\Client();

    $url= "https://api.phone.com/v4/accounts/{$this->account_id}/listeners";
    $data= [
      'type' => 'callback',
      'event_type' => 'sms.in',
      'callbacks' => [
        [
          'role' => 'main',
          'url' => $this->webhook_url,
          'verb' => 'POST',
        ]
      ]
    ];

    $res= $client->request('POST', $url, [
                             //'debug' => true,
                             'json' => $data,
                             'headers' => [
                               'authorization' => "Bearer {$this->token}",
                               'cache-control' => "no-cache",
                             ],
                           ]);

    $body= $res->getBody();

    return json_decode($body);
  }
}
