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

  public function createTracker($tracking_code, $carrier) {
    $tracker= \EasyPost\Tracker::create([
      'tracking_code' => $tracking_code,
      'carrier' => $carrier,
    ]);
    return $tracker->id;
  }

  public function createParcel($details) {
    return \EasyPost\Parcel::create($details);
  }

  public function createShipment($details) {
    return \EasyPost\Shipment::create($details);
  }

  public function getShipment($shipment) {
    return \EasyPost\Shipment::retrieve($shipment->method_id);
  }

  public function createReturn($shipment) {
    $ep= \EasyPost\Shipment::retrieve($shipment->method_id);
    $details= [
      'to_address' => $ep->from_address,
      'from_address' => $ep->to_address,
      'parcel' => $ep->parcel,
      'is_return' => true,
    ];
    return \EasyPost\Shipment::create($details);
  }

  public function getTrackerUrl($shipment) {
    $tracker= \EasyPost\Tracker::retrieve($shipment->tracker_id);
    return $tracker->public_url;
  }

  public function createBatch($shipments) {
      return \EasyPost\Batch::create([ 'shipments' => $shipments ]);
  }

  public function refundShipment($shipment) {
    $ep= \EasyPost\Shipment::retrieve($shipment->method_id);

    return $ep->refund();
  }
}
