<?php
namespace Scat\Service;

class Shipping
{
  private $webhook_url;
  private $data;
  private $shippo_tracking_baseurl;

  public function __construct(Config $config, Data $data) {
    $this->data= $data;
    \EasyPost\EasyPost::setApiKey($config->get('shipping.key'));
    \Shippo::setApiKey($config->get('shipping.shippo_key'));
    $this->shippo_tracking_baseurl=
      $config->get('shipping.shippo_tracking_baseurl');

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

  public function createTracker($method, $tracking_code, $carrier) {
    switch ($method) {
    case 'easypost':
      $tracker= \EasyPost\Tracker::create([
        'tracking_code' => $tracking_code,
        'carrier' => $carrier,
      ]);
      return $tracker->id;
    case 'shippo':
      $tracker= \Shippo_Track::create([
        'carrier' => $carrier,
        'tracking_number' => $tracking_code,
      ]);
      return $tracker->carrier . '/' . $tracker->tracking_number;
    default:
      throw new \Exception("Didn't understand shipment method '{$method}'");
    }
  }

  public function createShipment($details) {
    return \EasyPost\Shipment::create($details);
  }

  public function getShipment($shipment) {
    if ($shipment->method == 'easypost') {
      return \EasyPost\Shipment::retrieve($shipment->method_id);
    } else {
      return \Shippo_Shipment::retrieve($shipment->method_id);
    }
  }

  public function getTrackerUrl($shipment) {
    switch ($shipment->method) {
    case 'easypost':
      $tracker= \EasyPost\Tracker::retrieve($shipment->tracker_id);
      return $tracker->public_url;
    case 'shippo':
    error_log("baseurl {$this->shippo_tracking_baseurl}\n");
      return $this->shippo_tracking_baseurl . $shipment->tracker_id;
    default:
      throw new \Exception("Didn't understand shipment method '{$method}'");
    }
  }
}

