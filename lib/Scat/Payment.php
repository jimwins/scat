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
    'discount' => "Discount",
    'bad' => "Bad Debt",
    'donation' => "Donation",
    'withdrawal' => "Withdrawal",
    'internal' => "Internal",
  );
}
