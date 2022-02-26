<?php
namespace Scat\Model;

class Payment extends \Scat\Model {
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
    'postmates' => "Postmates",
    'venmo' => "Venmo",
    'loyalty' => "Loyalty Reward",
    'discount' => "Discount",
    'bad' => "Bad Debt",
    'donation' => "Donation",
    'withdrawal' => "Withdrawal",
    'internal' => "Internal",
  );

  function pretty_method() {
    switch ($this->method) {
    case 'stripe':
      if (!$this->cc_type) {
        return 'Paid by ' . self::$methods[$this->method];
      }
      /* fall through since we know credit card info */
    case 'credit':
      return 'Paid by ' . $this->cc_type .
             ($this->cc_lastfour ? ' ending in ' . $this->cc_lastfour : '');
    case 'discount':
      if ($this->discount) {
        return sprintf("Discount (%g%%)", $this->discount);
      } else {
        return 'Discount';
      }
    case 'change':
      return 'Change';
    default:
      return 'Paid by ' . self::$methods[$this->method];
    }
  }

  public function txn() {
    return $this->belongs_to('Txn')->find_one();
  }

  public function data() {
    return json_decode($this->data);
  }
}
