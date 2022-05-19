<?php
namespace Scat\Service;

class Stripe {
  private $public_key, $secret_key, $webhook_secret;

  public function __construct(Config $config) {
    $this->public_key= $config->get('stripe.key');
    $this->secret_key= $config->get('stripe.secret_key');
    $this->webhook_secret= $config->get('stripe.webhook_secret');
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

    if ($cart->stripe_payment_intent_id) {
      $paymentIntent= $stripe->paymentIntents->retrieve(
        $cart->stripe_payment_intent_id
      );
      if ($paymentIntent) {
        return $paymentIntent;
      }
    }

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

    $cart->stripe_payment_intent_id= $paymentIntent->id;

    return $paymentIntent;
  }

  public function updatePaymentIntent($payment_intent_id, $details) {
    $stripe= $this->getClient();
    return $stripe->paymentIntents->update($payment_intent_id, $details);
  }

  public function createCustomer($details) {
    $stripe= $this->getClient();
    return $stripe->customers->create($details);
  }

  public function updateCustomer($customer_id, $details) {
    $stripe= $this->getClient();
    return $stripe->customers->update($customer_id, $details);
  }

  public function constructWebhookEvent($body, $signature) {
    \Stripe\Stripe::setApiKey($this->secret_key);

    return \Stripe\Webhook::constructEvent(
      $body,
      $signature,
      $this->webhook_secret
    );
  }
}
