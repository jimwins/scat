<?php
namespace Scat\Service;

class Newsletter
{
  protected $key;
  protected $webhook_url;

  public function __construct(
    private Config $config
  ) {
    $this->key= $config->get('mailerlite.key');
    if (!$this->key) {
      throw new \Exception("Mailerlite API key is not configured.");
    }
    $this->webhook_url= $config->get('mailerlite.webhook_url');
    if (!$this->webhook_url) {
      throw new \Exception("URL for Mailerlite webhooks is not configured.");
    }
  }

  public function registerWebhooks() {
    $client= new \GuzzleHttp\Client();

    $data= [
      'url' => $this->webhook_url,
      'events' => [
        'subscriber.created',
        'subscriber.updated',
        'subscriber.unsubscribed',
        'subscriber.added_to_group',
        'subscriber.removed_from_group',
      ]
    ];

    $url= "https://connect.mailerlite.com/api/webhooks";

    $res= $client->request('POST', $url, [
                             //'debug' => true,
                             'json' => $data,
                             'headers' => [
                               'Authorization' => "Bearer {$this->key}",
                               'cache-control' => "no-cache",
                             ],
                           ]);

    $body= $res->getBody();

    return json_decode($body);
  }


  public function signup($email, $name) {
    $groupsApi= (new \MailerLiteApi\MailerLite($this->key))->groups();

    $subscriber= [
      'email' => $email,
      'fields' => [
        'name' => $name,
      ]
    ];

    $groupsApi->addSubscriber(
      $this->config->get('mailerlite.group_id'),
      $subscriber
    );
  }
}
