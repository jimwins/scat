<?php
namespace Scat\Service;

class Shipping
{
  private $webhook_url;

  public function __construct(Config $config) {
    \EasyPost\EasyPost::setApiKey($config->get('shipping.key'));

    $this->webhook_url= $config->get('shipping.webhook_url');
  }

  public function registerWebhook() {
    return \EasyPost\Webhook::create([ 'url' => $this->webhook_url ]);
  }

  public function createAddress($details) {
    return \EasyPost\Address::create($details);
  }

  public function retrieveAddress($address_id) {
    return \EasyPost\Address::retrieve($address_id);
  }

  public function createTracker($details) {
    return \EasyPost\Tracker::create($details);
  }

  public function getTracker($tracker_id) {
    return \EasyPost\Tracker::retrieve($tracker_id);
  }
}

