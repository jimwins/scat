<?php
namespace Scat\Model;

class Cart extends \Scat\Model {
  public static $_table= 'sale';

  private $_totals;

  public function items() {
    return $this->has_many('CartLine');
  }

  public function payments() {
    return $this->has_many('CartPayment');
  }

  public function shipping_address($address= []) {
    if (!empty($address)) {
      $new= $this->belongs_to('CartAddress', 'shipping_address_id')
                 ->create($address);
      $new->save();
      $this->shipping_address_id= $new->id;

      /* Passing in a new address always nukes our shipping costs */
      $this->shipping_method= NULL;
      $this->shipping= 0;
      $this->shipping_tax= 0;

      /* BUT DOES NOT SAVE */
    }
    return $this->belongs_to('CartAddress', 'shipping_address_id')->find_one();
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
          WHERE sale.id = {$this->id}) t";

    $this->orm->raw_execute($q);
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

  public function ready_for_payment() {
    return $this->shipping_method && $this->tax_calculated;
  }

  public function flushTotals() {
    $this->_totals= null;
  }

  public function get_shipping_box() {
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

  public function has_hazmat_items() {
    return $this->items()->join('item', [ 'item.id', '=', 'sale_item.item_id' ])->where_gt('item.hazmat', 0)->count();
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

  public function recalculateTax(\Scat\Service\Tax $tax) {
    $address= $this->shipping_address();

    if (!$address) {
      $this->tax_calculated= null;
      return;
    }

    $zip= explode('-', $address->zip);

    // Look up all non-returned items
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
        'Address2' => $address->address2,
        'Address1' => $address->address1,
      ],
      'cartItems' => [],
    ];

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

  public function sale_price() {
    return $this->calcSalePrice(
      $this->retail_price,
      $this->discount_type,
      $this->discount
    );
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
}
