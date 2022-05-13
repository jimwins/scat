<?php
namespace Scat\Model;

class Cart extends \Scat\Model {
  public static $_table= 'sale';

  private $_totals;

  public function items() {
    return $this->has_many('CartLine');
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
