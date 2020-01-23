<?php
namespace Scat;

class Payment extends \Model {
  public static $methods= array(
    'cash' => "Cash",
    'change' => "Change",
    'credit' => "Credit Card",
    'square' => "Square",
    'stripe' => "Stripe",
    'gift' => "Gift Card",
    'check' => "Check",
    'dwolla' => "Dwolla",
    'paypal' => "PayPal",
    'amazon' => "Amazon Pay",
    'eventbrite' => "Eventbrite",
    'discount' => "Discount",
    'bad' => "Bad Debt",
    'donation' => "Donation",
    'withdrawal' => "Withdrawal",
    'internal' => "Internal",
  );

  function pretty_method() {
    switch ($this->method) {
    case 'credit':
      return 'Paid by ' . $this->cc_type . ' ending in ' . $this->cc_lastfour;
    case 'discount':
      return sprintf("Discount (%d%%)", $this->discount);
    case 'change':
      return 'Change';
    default:
      return 'Paid by ' . self::$methods[$this->method];
    }
  }
}
