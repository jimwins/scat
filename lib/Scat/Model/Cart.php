<?php
namespace Scat\Model;

class Cart extends \Scat\Model {
  public static $_table= 'sale';

  private $_totals;

  /* A couple of helpers to act like a Txn model for templates */
  public function person() {
    return null;
  }

  public function type() {
    return 'customer';
  }

  public function items() {
    return $this->has_many('CartLine');
  }

  public function notes() {
    return $this->has_many('CartNote');
  }

  public function payments() {
    return $this->has_many('CartPayment');
  }

  public function shipping_address($address= []) {
    return $this->belongs_to('CartAddress', 'shipping_address_id')->find_one();
  }

  public function updateShippingAddress(
    \Scat\Service\Shipping $shipping,
    $address
  ) {
    /* TODO Move a bunch of this to Shipping service? Address model? */

    // TODO check if new data matches our address and just ignore update
    $new= $this->belongs_to('CartAddress', 'shipping_address_id')
               ->create($address);

    $easypost_params= [
      "verify" => [ "delivery" ],
      "name" => $new->name,
      "company" => $new->company,
      "street1" => $new->street1,
      "street2" => $new->street2,
      "city" => $new->city,
      "state" => $new->state,
      "zip" => $new->zip,
      "country" => "US",
      "phone" => $new->phone,
    ];

    $easypost= $shipping->createAddress($easypost_params);

    $new->easypost_id= $easypost->id;
    $new->verified= $easypost->verifications->delivery->success ? '1' : '0';
    if ($new->verified &&
        $easypost->verifications->delivery->details->longitude)
    {
      $distance= haversineGreatCircleDistance(
        34.043810, -118.250320, // XXX hardcoded location
        $easypost->verifications->delivery->details->latitude,
        $easypost->verifications->delivery->details->longitude,
        3959 /* want miles */
      );

      $new->distance= $distance;
      $new->latitude= $easypost->verifications->delivery->details->latitude;
      $new->longitude= $easypost->verifications->delivery->details->longitude;
    }

    $new->save();

    $this->shipping_address_id= $new->id;

    /* Passing in a new address always nukes our shipping costs */
    $this->shipping_method= NULL;
    $this->shipping= 0;
    $this->shipping_tax= 0;
  }

  public function closed() {
    return false; // TODO
  }

  private function _loadTotals() {
    if ($this->_totals) return $this->_totals;

    /* turn off logging here, it's just too much */
    $this->orm->configure('logging', false);

    $q= "SELECT ordered,
                taxed AS taxed,
                untaxed AS untaxed,
                taxed + untaxed AS subtotal,
                tax,
                taxed + untaxed + shipping + tax + shipping_tax AS total,
                IFNULL(total_paid, 0.00) AS total_paid
          FROM (SELECT
                shipping, shipping_tax,
                SUM(quantity) AS ordered,
                SUM(IF(sale_item.tax > 0, 0, 1) *
                    quantity *
                    scat.sale_price(retail_price, discount_type, discount))
                  AS untaxed,
                SUM(IF(sale_item.tax > 0, 1, 0) *
                    quantity *
                    scat.sale_price(retail_price, discount_type, discount))
                  AS taxed,
                SUM(tax) AS tax,
                CAST((SELECT SUM(amount)
                        FROM sale_payment
                       WHERE sale.id = sale_payment.sale_id)
                     AS DECIMAL(9,2)) AS total_paid
           FROM sale
           LEFT JOIN sale_item ON (sale.id = sale_item.sale_id)
          WHERE sale.id = ?) t";

    $this->orm->raw_execute($q, [ $this->id ]);
    $st= $this->orm->get_last_statement();

    $this->orm->configure('logging', true);

    $this->_totals= $st->fetch(\PDO::FETCH_ASSOC);

    return $this->_totals;
  }

  public function taxed() {
    return $this->_loadTotals()['taxed'];
  }

  public function subtotal() {
    return $this->_loadTotals()['subtotal'];
  }

  public function tax() {
    return $this->_loadTotals()['tax'];
  }

  public function total() {
    return $this->_loadTotals()['total'];
  }

  public function total_paid() {
    return $this->_loadTotals()['total_paid'];
  }

  public function due() {
    $total= $this->_loadTotals();
    return $total['total'] - $total['total_paid'];
  }

  public function ordered() {
    $total= $this->_loadTotals();
    return $total['ordered'];
  }

  public function loyalty_used() {
    return $this->payments()->where('method', 'loyalty')->find_one();
  }

  public function loyalty_reward_available($points) {
    return self::factory('LoyaltyReward')
                ->where_lte('cost', $points)
                ->order_by_desc('cost')
                ->find_one();
  }

  public function ready_for_payment() {
    return $this->shipping_method && $this->tax_calculated;
  }

  public function flushTotals() {
    $this->_totals= null;
  }

  public function get_item_dims() {
    $items= [];

    foreach ($this->items()->find_many() as $line) {
      if ($line->quantity <= 0) continue;

      $item= $line->item();

      if (!$item->length || !$item->height || !$item->width) {
        return null;
      }

      $dims= [
        'length' => $item->length,
        'height' => $item->height,
        'width' => $item->width
      ];

      $items+= array_fill(0, $line->quantity, $dims);
    }

    return $items;
  }

  public function get_shipping_box() {
    $items= $this->get_item_dims();
    if (!$items) return null;
    return \Scat\Service\Shipping::get_shipping_box($items);
  }

  public function get_shipping_weight() {
    $weight= 0;
    foreach ($this->items()->find_many() as $line) {
      $item= $line->item();
      if (isset($item->weight)) {
        $weight+= $line->quantity + $item->weight;
      } else {
        return NULL; /* Unable to calculate weight */
      }
    }
    return $weight;
  }

  public function eligible_for_free_shipping() {
    foreach ($this->items()->find_many() as $line) {
      if (!$line->item()->can_ship_free()) {
        return false; /* It just takes one. */
      }
    }
    return true;
  }

  public function has_hazmat_items() {
    return $this->items()->join('item', [ 'item.id', '=', 'sale_item.item_id' ])->where_gt('item.hazmat', 0)->count();
  }

  public function has_truck_only_items() {
    return $this->items()->join('item', [ 'item.id', '=', 'sale_item.item_id' ])->where_gt('item.oversized', 0)->count();
  }

  public function has_incomplete_items() {
    return $this->items()->join('item', [ 'item.id', '=', 'sale_item.item_id' ])->where_raw('IFNULL(weight, 0) = 0 OR IFNULL(length, 0) = 0 OR IFNULL(width, 0) = 0 OR IFNULL(height, 0) = 0')->count();
  }

  public function recalculate(
    \Scat\Service\Shipping $shipping,
    \Scat\Service\Tax $tax
  ) {
    $this->recalculateShipping($shipping);
    $this->recalculateTax($tax);
  }

  public function recalculateShipping(\Scat\Service\Shipping $shipping)
  {
    $address= $this->shipping_address();

    if (!$address) {
      $this->shipping_options= null;
      $this->shipping_method= null;
      $this->shipping= 0.00;
      $this->shipping_tax= 0.00;

      return;
    }

    /* Curbside pickup */
    if ($address->id == 1) {
      $this->shipping_options= null;
      $this->shipping_method= 'pickup';
      $this->shipping= 0.00;
      $this->shipping_tax= 0.00;

      return;
    }

    $this->shipping_options= $shipping->get_shipping_options($this, $address);

    $default= @$this->shipping_options['default'];

    if ($default) {
      $this->shipping_method= 'default';
      $this->shipping= $default['rate'];
      $this->shipping_tax= 0.00;
    } else {
      $this->shipping_method= null;
      $this->shipping= 0.00;
      $this->shipping_tax= 0.00;
    }
  }

  /* Convert shipping_options to/from JSON on the way in & out */
  public function __get($key) {
    $value= parent::__get($key);
    if ($key == 'shipping_options') {
      return json_decode($value, true);
    }
    return $value;
  }

  public function __set($key, $value= null) {
    if ($key == 'shipping_options' && $value !== null) {
      $value= json_encode($value);
    }
    if ($key == 'shipping_method' && $value !== null && $value != 'pickup') {
      $option= @$this->shipping_options[$value];
      if (!$option) {
        throw new \Exception("No such shipping option '$value'");
      }
      $this->shipping= $option['rate'];
      $this->shipping_tax= 0;
    }
    return parent::__set($key, $value);
  }

  public function generateCartItems() {
    $cartItems= []; $index_map= [];
    $n= 1;

    foreach ($this->items()->find_many() as $i) {
      $tic= $i->tic;
      $index= ($tic == '11000') ? 0 : $n++;
      $index_map[$index]= $i->id;
      $cartItems[]= [
        'Index' => $index,
        'ItemID' => ($tic == '11000') ? 'shipping' : $i->item_id,
        'TIC' => $tic,
        'Price' => $i->sale_price(),
        'Qty' => $i->quantity,
      ];
    }

    if ($cartItems && $this->shipping > 0) {
      $cartItems[]= [
        'Index' => 0,
        'ItemID' => 'shipping',
        'TIC' => '11000',
        'Price' => $this->shipping,
        'Qty' => 1,
      ];
    }

    return [ $cartItems, $index_map ];
  }

  public function flushTax() {
    foreach ($this->items()->find_many() as $line) {
      if ($line->tax) {
        $line->tax= 0;
        $line->save();
      }
    }
    $this->tax_calculated= null;
    $this->shipping_tax= 0;
  }

  public function recalculateTax(\Scat\Service\Tax $tax) {
    $address= $this->shipping_address();

    /* No address? Forget all we had. */
    if (!$address) {
      $this->flushTax();
      $this->flushTotals();
      return;
    }

    if ($this->tax_exemption == 'manual') {
      $this->flushTax();
      $this->set_expr('tax_calculated', 'NOW()');
      $this->flushTotals();
      return;
    }

    $zip= explode('-', $address->zip);

    $data= [
      'customerId' => $this->person_id ?: 0,
      'cartId' => $this->uuid,
      'deliveredBySeller' => false,
      // XXX get from default shipping address
      'origin' => [
        'Zip4' => '1320',
        'Zip5' => '90014',
        'State' => 'CA',
        'City' => 'Los Angeles',
        'Address2' => '',
        'Address1' => '645 S Los Angeles St',
      ],
      'destination' => [
        'Zip4' => $zip[1] ?? '',
        'Zip5' => $zip[0],
        'State' => $address->state,
        'City' => $address->city,
        'Address2' => $address->street2,
        'Address1' => $address->street1,
      ],
      'cartItems' => [],
    ];

    if ($this->tax_exemption) {
      $data['exemptCert']= [ 'CertificateID' => $this->tax_exemption ];
    }

    list($cartItems, $index_map)= $this->generateCartItems();

    $data['cartItems']= $cartItems;

    // Done if there's nothing to look up
    if (!count($data['cartItems'])) return;

    $response= $tax->lookup($data);

    if ($response->ResponseType < 2) {
      throw new \Exception($response->Messages[0]->Message);
    }

    foreach ($response->CartItemsResponse as $i) {
      if ($i->CartItemIndex == 0) {
        $this->shipping_tax= $i->TaxAmount;
      } else {
        $line= $this->items()->find_one($index_map[$i->CartItemIndex]);
        $line->tax= $i->TaxAmount;
        $line->save();
      }
    }

    $this->set_expr('tax_calculated', 'NOW()');
    $this->flushTotals();
  }

  public function addPayment($method, $amount, $captured, $data) {
    $payment= $this->payments()->create([
      'sale_id' => $this->id,
      'method' => $method,
      'amount' => $amount,
    ]);
    if ($data) {
      $payment->data= json_encode($data);
    }
    if ($captured) {
      $payment->set_expr('captured', 'NOW()');
    }
    $payment->set_expr('processed', 'NOW()');
    $payment->save();

    $this->flushTotals();

    if ($this->due() <= 0) {
      $this->status= 'paid';
    }
  }

  public function removePayments($method) {
    $this->payments()
          ->where('method', $method)->where_null('captured')->delete_many();
    $this->flushTotals();
  }

  /* Did this make our total less than loyalty or gift card applied?
   * If so, remove them until we still have a balance due. */
  public function ensureBalanceDue() {
    // TODO bail if cart finalized

    if ($this->due() < 0) {
      if ($this->loyalty_used()) {
        $this->removePayments('loyalty');
      }
      if ($this->due() < 0 &&
          $this->payments()->where('method', 'gift')->find_one())
      {
        $this->removePayments('gift');
      }
    }

    // TODO return useful information
  }

  public function as_array() {
    $data= parent::as_array();
    $data['subtotal']= $this->subtotal();
    $data['tax']= $this->tax();
    $data['total']= $this->total();
    $data['due']= $this->due();
    $data['ready_for_payment']= $this->ready_for_payment();
    return $data;
  }
}

class CartAddress extends \Scat\Model {
  public static $_table= 'sale_address';
}

class CartLine extends \Scat\Model {
  public static $_table= 'sale_item';

  public function cart() {
    return $this->belongs_to('Cart')->find_one();
  }

  public function item() {
    return $this->belongs_to('Item')->find_one();
  }

  /* A few helpers to look more like a TxnLine */
  public function code() {
    return $this->item()->code;
  }

  public function name() {
    return $this->override_name ?: $this->item()->name;
  }

  public function ordered() {
    return $this->quantity * -1;
  }

  public function sale_price() {
    return $this->calcSalePrice(
      $this->retail_price,
      $this->discount_type,
      $this->discount
    );
  }

  public function pricing_detail() {
    if (!$this->discount_type) return '';
    $item= $this->item();
    return ($item->retail_price ? 'MSRP $' : 'List $') .
           $this->retail_price .
           ($this->discount_type == 'percentage' ?
            ' / Sale: ' . sprintf('%.0f', $this->discount) . '% off' :
            ($this->discount_type == 'relative' ?
             ' / Sale: $' . $this->discount . ' off' :
             ''));
  }

  public function updateQuantity($quantity) {
    $item= $this->item();
    $cart= $this->cart();

    $this->quantity= $quantity;
    if ($item->purchase_quantity && $this->quantity % $item->purchase_quantity)
    {
      $this->quantity=
        ((floor($this->quantity / $item->purchase_quantity) + 1) *
          $item->purchase_quantity);
    }
    if ($item->no_backorder && $this->quantity > $item->stock) {
      $this->quantity= $item->stock;
    }

    /* Was this a kit? Need to adjust quantities of kit items */
    // This assumes kit contents haven't changed
    if ($item->is_kit) {
      if ($this->id) {
        error_log("Updating quantities for kit {$item->code} ({$this->id}) on {$cart->uuid}");
        $q= "UPDATE sale_item, kit_item
                SET sale_item.quantity = ? * kit_item.quantity
              WHERE sale_id = ?
                AND kit_item.kit_id = ?
                AND sale_item.item_id = kit_item.item_id";
        $this->orm->for_table('sale_item')->raw_execute($q, [
          $this->quantity, $cart->id, $item->id
        ]);
      } else {
        error_log("Inserting items for kit {$item->code} ({$this->id}) on {$cart->uuid}");
        $q= "INSERT INTO sale_item
                    (sale_id, item_id, kit_id, quantity, retail_price, tax)
             SELECT ?,
                    item_id,
                    kit_id,
                    ? * quantity,
                    0.00,
                    0.00
               FROM kit_item
              WHERE kit_item.kit_id = ?";
        $this->orm->for_table('sale_item')->raw_execute($q, [
          $cart->id, $this->quantity, $item->id
        ]);
      }
    }

    error_log("Updated to {$this->quantity} {$item->code} ({$this->id}) on {$cart->uuid}");
  }

  public function delete() {
    $item= $this->item();
    $cart= $this->cart();

    if ($item->is_kit) {
      error_log("Removing kit {$item->code} ({$this->id}) items from {$cart->uuid}\n");
      $cart->items()->where('kit_id', $this->item_id)->delete_many();
    }

    error_log("Removing {$item->code} ({$this->id}) from {$cart->uuid}\n");

    return parent::delete();
  }
}

class CartNote extends \Scat\Model {
  public static $_table= 'sale_note';

  public function cart() {
    return $this->belongs_to('Cart')->find_one();
  }
}

class CartPayment extends \Scat\Model {
  public static $_table= 'sale_payment';

  public function cart() {
    return $this->belongs_to('Cart')->find_one();
  }

  function pretty_method() {
    $methods= \Scat\Model\Payment::$methods;
    switch ($this->method) {
    case 'stripe':
      if (!$this->cc_type) {
        return 'Paid by ' . $methods[$this->method];
      }
      /* fall through since we know credit card info */
    case 'credit':
      $data= $this->data();
      return 'Paid by ' . $data->cc_brand .
             ($data->cc_last4 ? ' ending in ' . $data->cc_last4 : '');
    case 'discount':
      if ($this->discount) {
        return sprintf("Discount (%g%%)", $this->discount);
      } else {
        return 'Discount';
      }
    case 'change':
      return 'Change';
    default:
      return 'Paid by ' . $methods[$this->method];
    }
  }

  public function data() {
    return json_decode($this->data);
  }

  public function jsonSerialize() {
    $data= parent::jsonSerialize();
    $data['data']= json_decode($this->data);
    return $data;
  }
}

/**
 * from: https://stackoverflow.com/a/10054282
 * Calculates the great-circle distance between two points, with
 * the Haversine formula.
 * @param float $latitudeFrom Latitude of start point in [deg decimal]
 * @param float $longitudeFrom Longitude of start point in [deg decimal]
 * @param float $latitudeTo Latitude of target point in [deg decimal]
 * @param float $longitudeTo Longitude of target point in [deg decimal]
 * @param float $earthRadius Mean earth radius in [m]
 * @return float Distance between points in [m] (same as earthRadius)
 */
function haversineGreatCircleDistance(
  $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
{
  // convert from degrees to radians
  $latFrom = deg2rad($latitudeFrom);
  $lonFrom = deg2rad($longitudeFrom);
  $latTo = deg2rad($latitudeTo);
  $lonTo = deg2rad($longitudeTo);

  $latDelta = $latTo - $latFrom;
  $lonDelta = $lonTo - $lonFrom;

  $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
    cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
  return $angle * $earthRadius;
}
