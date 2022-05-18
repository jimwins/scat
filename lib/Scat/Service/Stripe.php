<?php
namespace Scat\Service;

class Stripe {
  private $public_key, $secret_key;

  public function __construct(Config $config) {
    $this->public_key= $config->get('stripe.key');
    $this->secret_key= $config->get('stripe.secret_key');
  }

  private function getClient() {
    return new \Stripe\StripeClient([
      'api_key' => $this->secret_key,
      'stripe_version' => "2020-08-27;link_beta=v1",
    ]);
  }

  public function getPublicKey() {
    return $this->public_key;
  }

  public function getPaymentIntent($cart) {
    $stripe= $this->getClient();

    $paymentIntent= $stripe->paymentIntents->create([
      'payment_method_types' => [
        'link',
        'card'
      ],
      'metadata' => [
        "sale_id" => $cart->id,
        "sale_uuid" => $cart->uuid,
      ],
      'amount' => $cart->due() * 100,
      'currency' => 'usd',
    ]);

    return $paymentIntent;
  }
}
