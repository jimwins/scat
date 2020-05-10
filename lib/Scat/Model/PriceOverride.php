<?php
namespace Scat\Model;

class PriceOverride extends \Model {
  function product() {
    return ($this->pattern_type == 'product' ? 
            $this->belongs_to('Product', 'pattern')->find_one() :
            null);
  }

  public function setDiscount($discount) {
    $discount= preg_replace('/^\\$/', '', $discount);
    if (preg_match('/^(\d*)(\/|%)( off)?$/', $discount, $m)) {
      $discount = (float)$m[1];
      $discount_type = "percentage";
    }
    elseif (preg_match('/^\+(\d*)(\/|%)( off)?$/', $discount, $m)) {
      $discount = (float)$m[1];
      $discount_type = "additional_percentage";
    } elseif (preg_match('/^(\d*\.?\d*)$/', $discount, $m)) {
      $discount = (float)$m[1];
      $discount_type = "fixed";
    } elseif (preg_match('/^\$?(\d*\.?\d*)( off)?$/', $discount, $m)) {
      $discount = (float)$m[1];
      $discount_type = "relative";
    } elseif (preg_match('/^-\$?(\d*\.?\d*)$/', $discount, $m)) {
      $discount = (float)$m[1];
      $discount_type = "relative";
    } elseif (preg_match('/^(def|\.\.\.)$/', $discount)) {
      $discount= null;
      $discount_type= null;
    } else {
      throw new \Exception("Did not understand discount.");
    }
    $this->discount= $discount;
    $this->discount_type= $discount_type;
  }
}
