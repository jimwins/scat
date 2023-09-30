<?php
namespace Scat\Service;

use Scat\Distance;

class Shipping
{
  private $client;
  private $webhook_url;

  # TODO put this in a database table
  # width, height, depth, weight (lb), cost
  static private $all_boxes= [
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
    [ 42,    32,     5,    2.09, 2.50 ], # guess
    [ 54,     4,     4,    0.88, 0.00 ],
    [ 87,     4,     4,    1.28, 0.00 ],
  ];

  # TODO put this in a database table
  static public $test_addresses= [
    [
      'name' => 'Test Address',
      'street1' => '76 9TH AVE',
      'city' => 'NEW YORK',
      'state' => 'NY',
      'zip' => '10011',
    ],
    [
      'name' => 'Test Address',
      'street1' => '401 N TRYON ST',
      'city' => 'CHARLOTTE',
      'state' => 'NC',
      'zip' => '28202',
    ],
    [
      'name' => 'Test Address',
      'street1' => '2332 GALIANO ST',
      'city' => 'CORAL GABLES',
      'state' => 'FL',
      'zip' => '33134',
    ],
    [
      'name' => 'Test Address',
      'street1' => '2590 PEARL ST',
      'city' => 'BOULDER',
      'state' => 'CO',
      'zip' => '80302',
    ],
    [
      'name' => 'Test Address',
      'street1' => '1600 AMPHITHEATRE PKWY',
      'city' => 'MOUNTAIN VIEW',
      'state' => 'CA',
      'zip' => '94043',
    ],
    [
      'name' => 'Test Address',
      'street1' => '4021 VERNON AVE S',
      'city' => 'MINNEAPOLIS',
      'state' => 'MN',
      'zip' => '55416',
    ],
    [
      'name' => 'Test Address',
      'street1' => '201 COLORADO ST',
      'city' => 'AUSTIN',
      'state' => 'TX',
      'zip' => '78701',
    ],
    [
      'name' => 'Test Address',
      'street1' => '651 N 34TH ST',
      'city' => 'SEATTLE',
      'state' => 'WA',
      'zip' => '98103',
    ],
    [
      'name' => 'Test Address',
      'street1' => '4581 WEBB ST',
      'city' => 'PRYOR',
      'state' => 'OK',
      'zip' => '74361',
    ],
    [
      'name' => 'Test Address',
      'street1' => '201 S DIVISION ST',
      'city' => 'ANN ARBOR',
      'state' => 'MI',
      'zip' => '48104',
    ],
    [
      'name' => 'Test Address',
      'street1' => '364 S KING ST',
      'city' => 'HONOLULU',
      'state' => 'HI',
      'zip' => '96813',
    ],
    [
      'name' => 'Test Address',
      'street1' => '631 E INTERNATIONAL AIRPORT RD',
      'city' => 'ANCHORAGE',
      'state' => 'AK',
      'zip' => '99518',
    ],
  ];

  public function __construct(
    private Config $config,
    private Data $data,
    private Google $google,
  ) {
    $this->data= $data;
    $this->google= $google;

    $this->client= new \EasyPost\EasyPostClient($config->get('shipping.key'));
    $this->webhook_url= $config->get('shipping.webhook_url');
  }

  public function registerWebhook() {
    return $this->client->webhook->create([ 'url' => $this->webhook_url ]);
  }

  public function getDefaultFromAddress() {
    $address= $this->data->factory('Address')->find_one(1);

    try {
      $ep= $this->client->address->retrieve($address->easypost_id);
    } catch (\Exception $e) {
      $ep= $this->client->address->create($address->as_array());
      $address->easypost_id= $ep->id;
      $address->save();
    }

    return $ep;
  }

  public function createAddress($details) {
    return $this->client->address->create($details);
  }

  public function retrieveAddress($address_id) {
    return $this->client->address->retrieve($address_id);
  }

  public function getAddress($address) {
    if ($address->easypost_id) {
      return $this->client->address->retrieve($address->easypost_id);
    }

    $easypost_params= [
      "verify" => [ "delivery" ],
      "name" => $address->name,
      "company" => $address->company,
      "street1" => $address->street1,
      "street2" => $address->street2,
      "city" => $address->city,
      "state" => $address->state,
      "zip" => $address->zip,
      "country" => "US",
      "phone" => $address->phone,
    ];

    $easypost= $this->client->address->create($easypost_params);

    $address->easypost_id= $easypost->id;
    $address->verified= $easypost->verifications->delivery->success ? '1' : '0';
    if ($address->verified &&
        $easypost->verifications->delivery->details->longitude)
    {
      $distance= Distance::haversineGreatCircleDistance(
        34.043810, -118.250320, // XXX hardcoded location
        $easypost->verifications->delivery->details->latitude,
        $easypost->verifications->delivery->details->longitude,
        3959 /* want miles */
      );

      $address->distance= $distance;
      $address->latitude= $easypost->verifications->delivery->details->latitude;
      $address->longitude=
        $easypost->verifications->delivery->details->longitude;
    }

    $address->save();
  }

  public function createTracker($tracking_code, $carrier) {
    $tracker= $this->client->tracker->create([
      'tracking_code' => $tracking_code,
      'carrier' => $carrier,
    ]);
    return $tracker->id;
  }

  public function createParcel($details) {
    return $this->client->parcel->create($details);
  }

  public function createShipment($details, $apiKey= null, $withCarbonOffset= false) {
    return $this->client->shipment->create($details, $apiKey, $withCarbonOffset);
  }

  public function getShipment($shipment) {
    return $this->client->shipment->retrieve($shipment->method_id);
  }

  public function buyShipment($shipment, $rate) {
    return $this->client->shipment->buy($shipment->method_id, $rate);
  }

  public function createReturn($shipment) {
    $ep= $this->client->shipment->retrieve($shipment->method_id);
    // Keep original to/from, is_return signals to flip them
    $details= [
      'to_address' => $ep->to_address,
      'from_address' => $ep->from_address,
      'parcel' => $ep->parcel,
      'is_return' => true,
    ];
    return $this->client->shipment->create($details);
  }

  public function getTracker($shipment) {
    return $this->client->tracker->retrieve($shipment->tracker_id);
  }

  public function getTrackerUrl($shipment) {
    $tracker= $this->client->tracker->retrieve($shipment->tracker_id);
    return $tracker->public_url;
  }

  public function createBatch($shipments) {
      return $this->client->batch->create([ 'shipments' => $shipments ]);
  }

  public function refundShipment($shipment) {
    return $this->client->shipment->refund($shipment->method_id);
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

  static function get_shipping_box($items) {
    return self::fits_in_box(self::$all_boxes, $items);
  }

  function get_shipping_estimate($box, $weight, $hazmat, $dest) {
    $from= $this->getDefaultFromAddress();
    $to= $this->createAddress($dest);

    $options= [];
    if ($hazmat) {
      $options['hazmat']= 'LIMITED_QUANTITY';
    }

    $details= [
      'from_address' => $from,
      'to_address' => $to,
      'parcel' => [
        'length' => $box[0],
        'width' => $box[1],
        'height' => $box[2],
        'weight' => ceil(($weight + $box[3]) * 16),
      ],
      'options' => $options,
    ];

    $shipment= $this->createShipment($details, null, true);

    $best_rate= $method= null;

    foreach ($shipment->rates as $rate) {
      error_log("{$rate->carrier} / {$rate->service} = {$rate->rate}");
      if (in_array($rate->carrier, [ 'USPS' ]) &&
          in_array($rate->service, [ 'Priority', 'GroundAdvantage' ]))
      {
        /* No hazmat outside contintental US or by Priority. */
        if ($hazmat && $rate->service != 'GroundAdvantage' &&
            !self::state_in_continental_us($to->state))
        {
          continue;
        }
        if (!$best_rate || $rate->rate < $best_rate) {
          $method= "{$rate->carrier} / {$rate->service }";
          $best_rate= $rate->rate;
        }
      }

      if (in_array($rate->carrier, [ 'UPSDAP', 'UPS' ]) &&
          $rate->service == 'Ground')
      {
        /* No hazmat outside contintental US. */
        if ($hazmat && !self::state_in_continental_us($to->state)) {
          continue;
        }
        if (!$best_rate || $rate->rate < $best_rate) {
          $method= "{$rate->carrier} / {$rate->service }";
          $best_rate= $rate->rate;
        }
      }
    }

    if ($best_rate) {
      error_log("Selecting {$method} = {$best_rate} + $box[4]");
      return [
        (string)(new \Decimal\Decimal((string)$box[4]) + $best_rate),
        $method
      ];
    }

    return [ 0.00, null ];

  }

  public function get_shipping_options($cart, $address) {
    $box= $cart->get_shipping_box();
    $weight= $cart->get_shipping_weight();
    $hazmat= $cart->has_hazmat_items();

    $shipping_options= [];

    /* Can only calculate shipping if it fits in a box and has weight */
    if ($box && $weight) {
      error_log("getting options for $weight lb box: " . json_encode($box));
      $from= $this->getDefaultFromAddress();
      $to= $this->getAddress($address);

      $options= [];
      if ($hazmat) {
        $options['hazmat']= 'LIMITED_QUANTITY';
      }

      $details= [
        'from_address' => $from,
        'to_address' => $to,
        'parcel' => [
          'length' => $box[0],
          'width' => $box[1],
          'height' => $box[2],
          'weight' => ceil(($weight + $box[3]) * 16),
        ],
        'options' => $options,
      ];

      $shipment= $this->createShipment($details, null, true);

      /*
       * XXX
       * USPS Priority Mail could be used as a two_day or next_day
       * possibility, would need to get the rate, check the delivery_dates,
       * and slot it into the correct option category.
       */
      $acceptable_options= [
        'default' => [
          [ 'UPS', 'Ground' ],
          [ 'UPSDAP', 'Ground' ],
          [ 'USPS', 'GroundAdvantage' ],
        ],
        'two_day' => [
          [ 'UPS', '2ndDayAir' ],
          [ 'UPSDAP', '2ndDayAir' ],
        ],
        'next_day' => [
          [ 'UPS', 'NextDayAir' ],
          [ 'UPS', 'NextDayAirSaver' ],
          [ 'UPSDAP', 'NextDayAir' ],
          [ 'UPSDAP', 'NextDayAirSaver' ],
        ],
      ];

      if (!$hazmat) {
        /* We can use USPS Priority for non-hazmat items. */
        $acceptable_options['default'][]= [ 'USPS', 'Priority' ];
      } else {
        /* No express options for hazmat items. */
        unset($acceptable_options['two_day']);
        unset($acceptable_options['next_day']);
      }

      foreach ($shipment->rates as $rate) {
        error_log("{$rate->carrier} / {$rate->service} = {$rate->rate}");
        foreach ($acceptable_options as $method => $options) {
          foreach ($options as $option) {
            if ($rate->carrier == $option[0] && $rate->service == $option[1]) {
              if (!array_key_exists($method, $shipping_options) ||
                  $rate->rate < $shipping_options[$method]['rate'])
              {
                $shipping_options[$method]= $rate->__toArray(true);
              }
            }
          }
        }
      }

      /* Add box cost to rates */
      foreach ($shipping_options as $method => $rate) {
        // TODO use Decimal?
        $rate['rate']+= $box[4];
      }

      /* Set free shipping */
      if (isset($shipping_options['default']) &&
          $cart->eligible_for_free_shipping() &&
          self::state_in_continental_us($to->state) &&
          $cart->subtotal() >= 79)
      {
        $shipping_options['default']['rate']= 0.00;
      }
    } else {
      error_log("Couldn't calculate shipping box");
    }

    /* Get local delivery options */
    if ($this->in_delivery_area($address)) {
      list($delivery_cost, $delivery_method)=
        $this->get_delivery_estimate($address, $cart);
      if ($delivery_cost) {
        $shipping_options['local_delivery']= [
          'carrier' => 'Ship District',
          'vehicle' => $delivery_method,
          'rate' => $delivery_cost,
        ];
      } else {
        error_log("unable to calculate delivery cost");
      }
    } else {
        error_log("Not in local delivery area");
    }

    return $shipping_options;
  }

  static function get_base_local_delivery_rate($item_dim, $weight) {
    $truck_sizes= [
      'sm' => [ [ 30, 25, 16 ], [ 108, 4, 4 ] ],
      'md' => [ [ 46, 38, 36 ] ],
      'lg' => [ [ 74, 42, 36 ], [ 108, 8, 8 ] ],
      'xl' => [ [ 85, 56, 36 ] ],
      'xxl' => [ [ 150, 60, 60 ] ],
    ];

    $base= [
      'sm' => 13,
      'md' => 35,
      'lg' => 55,
      'xl' => 95,
      'xxl' => 170,
    ];

    $best= null;
    // figure out cargo size
    foreach ($truck_sizes as $name => $sizes) {
      if (self::fits_in_box($sizes, $item_dim)) {
        return $base[$name];
      }
    }

    return false;
  }

  static function address_is_po_box($address) {
    return preg_match('/po box/i', $address->street1. $address->street2);
  }

  static function state_in_continental_us($state) {
    return in_array($state, [
      // 'AK',
      'AL',
      'AZ',
      'AR',
      'CA',
      'CO',
      'CT',
      'DE',
      'FL',
      'GA',
      // 'HI',
      'ID',
      'IL',
      'IN',
      'IA',
      'KS',
      'KY',
      'LA',
      'ME',
      'MD',
      'MA',
      'MI',
      'MN',
      'MS',
      'MO',
      'MT',
      'NE',
      'NV',
      'NH',
      'NJ',
      'NM',
      'NY',
      'NC',
      'ND',
      'OH',
      'OK',
      'OR',
      'PA',
      // 'PR',
      'RI',
      'SC',
      'SD',
      'TN',
      'TX',
      'UT',
      'VT',
      'VA',
      'WA',
      'WV',
      'WI',
      'WY',
    ]);
  }

  /* Local delivery */
  public function in_delivery_area($address) {
    /* No distance? Calculate it. */
    if (!isset($address->distance)) {
      $from= $this->data->factory('Address')->find_one(1);
      $distance= Distance::haversineGreatCircleDistance(
        $from->latitude, $from->longitude,
        $address->latitude, $address->longitude,
        3959 /* want miles */
      );
    } else {
      $distance= $address->distance;
    }

    return $distance > 0 && $distance < 30;
  }

  public function get_delivery_estimate($address, $cart) {
    $truck_sizes= [
      'sm' => [ [ 30, 25, 16 ], [ 45, 25, 10 ], [ 108, 4, 8 ] ],
      'md' => [ [ 46, 38, 36 ] ],
      'lg' => [ [ 74, 42, 36 ], [ 108, 8, 8 ] ],
      'xl' => [ [ 85, 56, 36 ] ],
      'xxl' => [ [ 150, 60, 60 ] ],
    ];

    $base= [
      'sm' => 13,
      'md' => 35,
      'lg' => 55,
      'xl' => 95,
      'xxl' => 170,
    ];

    $item_dims= $cart->get_item_dims();

    if (!$item_dims) return;

    $best= null;
    // figure out cargo size
    foreach ($truck_sizes as $name => $sizes) {
      if ($this->fits_in_box($sizes, $item_dims)) {
        $best= $name;
        break;
      }
    }

    // figure out price
    if ($best) {
      list($miles, $minutes)= $this->get_truck_distance($address);
      if ($miles > 0) {
        $price= ($base[$best] + $miles + ($minutes / 2)) * 1.05;
        return [
          ceil($price) - 0.01,
          "local_{$best}"
        ];
      } else {
        error_log("Unable to figure out distance for destination.");
      }
    }
  }

  function get_truck_distance($address) {
    $from= $this->getDefaultFromAddress();
    $from_str= "{$from->street1}, " .
               "{$from->city}, {$from->state} {$from->zip}";

    $to_str= "{$address->street1}, " .
             "{$address->city}, {$address->state} {$address->zip}";

    return $this->google->getMapsDistanceMatrix($from_str, $to_str);
  }
}
