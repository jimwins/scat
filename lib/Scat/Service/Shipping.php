<?php
namespace Scat\Service;

class Shipping
{
  private $webhook_url;
  private $data;

  # TODO put this in a database table
  # width, height, depth, weight (lb), cost
  private $all_boxes= [
    [  5,     5,     3.5,  0.13, 0.39 ],
    [  9,     5,     3,    0.21, 0.53 ],
    [  9,     8,     8,    0.48, 0.86 ],
    [ 12.25,  3,     3,    0.19, 1.03 ],
    [ 10,     7,     5,    0.32, 0.82 ],
    [ 12,     9.5,   4,    0.44, 1.01 ],
    [ 12,     9,     9,    0.65, 0.98 ],
    [ 15,    12,     4,    0.81, 1.28 ],
    [ 15,    12,     8,    0.91, 1.59 ],
    [ 18,    16,     4,    1.16, 1.77 ],
    [ 18.75,  3,     3,    0.24, 1.24 ],
    [ 20.25, 13.25, 10.25, 1.21, 2.17 ],
    [ 22,    18,     6,    1.54, 2.32 ],
    [ 33,    19,     4.5,  2.09, 4.08 ],
    [ 34,    23,     5,    2.09, 2.50 ],
    [ 54,     4,     4,    0.88, 0.00 ],
    [ 87,     4,     4,    1.28, 0.00 ],
  ];

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
    // Keep original to/from, is_return signals to flip them
    $details= [
      'to_address' => $ep->to_address,
      'from_address' => $ep->from_address,
      'parcel' => $ep->parcel,
      'is_return' => true,
    ];
    return \EasyPost\Shipment::create($details);
  }

  public function getTracker($shipment) {
    return \EasyPost\Tracker::retrieve($shipment->tracker_id);
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

  static function fits_in_box($boxes, $items) {
    $laff= new \Cloudstek\PhpLaff\Packer();

    foreach ($boxes as $size) {
      $laff->pack($items, [
            'length' => $size[0],
            'width' => $size[1],
            'height' => $size[2],
      ]);

      $container= $laff->get_container_dimensions();

      if ($container['height'] <= $size[2] &&
          !count($laff->get_remaining_boxes()))
      {
        return $size;
      }
    }

    return false;
  }

  function get_shipping_box($items) {
    return self::fits_in_box($this->all_boxes, $items);
  }
}
