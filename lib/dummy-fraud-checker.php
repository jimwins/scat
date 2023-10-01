<?php
/* Just a dummy for static analysis. */

class FraudChecker {
  public function checkForFraud(\Scat\Model\Cart $cart, \Scat\Service\Stripe $stripe) : string {
    return "";
  }
}
