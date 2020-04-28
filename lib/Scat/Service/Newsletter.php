<?php
namespace Scat\Service;

class Newsletter
{
  protected $key;
  protected $webhook_url;

  public function __construct(Config $config) {
    $this->key= $config->get('newsletter.key');
    if (!$this->key) {
      throw new \Exception("Mailerlite API key is not configured.");
    }
    $this->webhook_url= $config->get('newsletter.webhook_url');
    if (!$this->webhook_url) {
      throw new \Exception("URL for Mailerlite webhooks is not configured.");
    }
  }

  public function registerWebhooks() {
    $client= new \GuzzleHttp\Client();

    $events= [
      'subscriber.create',
      'subscriber.update',
      'subscriber.unsubscribe',
      'subscriber.add_to_group',
      'subscriber.remove_from_group',
    ];

    $url= "https://api.mailerlite.com/api/v2/webhooks";
    $results= [];

    foreach ($events as $event) {
      $data= [
        'url' => $this->webhook_url,
        'event' => $event,
      ];

      $res= $client->request('POST', $url, [
                               //'debug' => true,
                               'json' => $data,
                               'headers' => [
                                 'X-Mailerlite-ApiKey' => $this->key,
                                 'cache-control' => "no-cache",
                               ],
                             ]);

      $body= $res->getBody();

      $results[]= json_decode($body);

    }

    return $results;
  }
}
