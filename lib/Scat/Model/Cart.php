<?php
namespace Scat\Model;

class Cart extends \Scat\Model {
  public static $_table= 'sale';

  public function items() {
    return $this->has_many('CartLine');
  }

  public function shipping_address() {
    return $this->belongs_to('CartAddress', 'shipping_address_id')->find_one();
  }

  public function closed() {
    return false; // TODO
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
