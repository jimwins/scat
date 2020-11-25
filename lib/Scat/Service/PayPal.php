<?php
namespace Scat\Service;

class PayPal {
  private $client_id, $secret;
  public $from_email, $from_name;

  public function __construct(Config $config) {
    $this->client_id= $config->get('paypal.client_id');
    $this->secret= $config->get('paypal.secret');
  }

  private function getClient() {
    if ($GLOBALS['DEBUG']) {
      $env= new \PayPalCheckoutSdk\Core\SandboxEnvironment(
        $this->client_id, $this->secret
      );
    } else {
      $env= new \PayPalCheckoutSdk\Core\ProductionEnvironment(
        $this->client_id, $this->secret
      );
    }

    return new \PayPalCheckoutSdk\Core\PayPalHttpClient($env);
  }

  public function refund($capture_id, $amount) {
    $paypal= $this->getClient();

    $req= new \PayPalCheckoutSdk\Payments\CapturesRefundRequest($capture_id);
    $req->body= [
      'amount' => [
        'value' => $amount,
        'currency_code' => 'USD',
      ],
    ];
    $req->prefer('return=representation');

    $res= $paypal->execute($req);

    return $res;
  }

  public function addTracker($payment, $tracker) {
    $paypal= $this->getClient();

    $charge= json_decode($payment->data);
    $capture_id= $charge->purchase_units[0]->payments->captures[0]->id;

    $req= new \PayPalHttp\HttpRequest('/v1/shipping/trackers-batch', 'POST');
    $req->headers["Content-Type"]= "application/json";
    $req->body= [
      'trackers' => [
        [
          'transaction_id' => $capture_id,
          'tracking_number' => $tracker->tracking_code,
          'status' => 'SHIPPED',
          'carrier' => strtoupper($tracker->carrier),
        ],
      ]
    ];

    $res= $paypal->execute($req);

    error_log("Added tracker to {$capture_id} for {$tracker->tracking_code} via {$tracker->carrier}\n");

    //error_log(json_encode($res) . "\n");

    return $res;
  }
}
