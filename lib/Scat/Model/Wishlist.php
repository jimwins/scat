<?php
namespace Scat\Model;

class Wishlist extends \Scat\Model {
  public function items() {
    return $this->has_many('WishlistItem');
  }
}

class WishlistItem extends \Scat\Model {
  public function wishlist() {
    return $this->belongs_to('Wishlist')->find_one();
  }

  public function item() {
    return $this->belongs_to('Item')->find_one();
  }
}
