<?php
namespace Scat\Model;

class LoyaltyReward extends \Scat\Model {
  public function item() {
    return $this->belongs_to('Item')->find_one();
  }
}
