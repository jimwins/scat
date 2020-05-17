<?php
namespace Scat\Service;

class Shipping
{
  private $webhook_url;
  private $data;

  public function __construct(Config $config, Data $data) {
    $this->data= $data;
    \EasyPost\EasyPost::setApiKey($config->get('shipping.key'));

    $this->webhook_url= $config->get('shipping.webhook_url');
  }

  public function registerWebhook() {
    return \EasyPost\Webhook::create([ 'url' => $this->webhook_url ]);
  }

  public function getDefaultFromAddress() {
    $address= $this->data->factory('Address')->find_one(1);

    try {
      $ep= \EasyPost\Address::retrieve($address->easypost_id);
    } catch (\Exception $e) {
      $ep= \EasyPost\Address::create($address->as_array());
      $address->easypost_id= $ep->id;
      $address->save();
    }

    return $ep;
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

  public function createShipment($details) {
    return \EasyPost\Shipment::create($details);
  }

  public function getShipment($shipment_id) {
    return \EasyPost\Shipment::retrieve($shipment_id);
  }

  public function getTracker($tracker_id) {
    return \EasyPost\Tracker::retrieve($tracker_id);
  }
}

