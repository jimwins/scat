<?php
namespace Scat\Service;

class PayPal {
  private $client_id, $secret, $webhook_id;
  public $from_email, $from_name;

  public function __construct(Config $config) {
    $this->client_id= $config->get('paypal.client_id');
    $this->secret= $config->get('paypal.secret');
    $this->webhook_id= $config->get('paypal.webhook_id');
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

  public function getClientId() {
    return $this->client_id;
  }

  public function getWebhookId() {
    return $this->webhook_id;
  }

  public function verifyWebhook($request) {
    // adapted from https://stackoverflow.com/a/62870569
    if ($this->webhook_id) {
      $headers= $request->getHeaders();
      $body= $request->getBody();

      $data= join('|', [
		  $headers['Paypal-Transmission-Id'][0],
		  $headers['Paypal-Transmission-Time'][0],
		  $this->webhook_id,
		  crc32($body) ]);

      $cert= file_get_contents($headers['Paypal-Cert-Url'][0]);
      $pubkey= openssl_pkey_get_public($cert);

      $sig= base64_decode($headers['Paypal-Transmission-Sig'][0]);

      $res= openssl_verify($data, $sig, $pubkey, 'sha256WithRSAEncryption');

      if ($res == 0) {
        throw new \Exception("Webhook signature validation failed.");
      } elseif ($res < 0) {
        throw new \Exception("Error validating signature: " . openssl_error_string());
      }
    }
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

    /* Force carrier to UPS if it was UPSDAP */
    $carrier= strtoupper($tracker->carrier);
    if ($carrier == 'UPSDAP') $carrier= 'UPS';

    $req= new \PayPalHttp\HttpRequest('/v1/shipping/trackers-batch', 'POST');
    $req->headers["Content-Type"]= "application/json";
    $req->body= [
      'trackers' => [
        [
          'transaction_id' => $capture_id,
          'tracking_number' => $tracker->tracking_code,
          'status' => 'SHIPPED',
          'carrier' => $carrier,
        ],
      ]
    ];

    $res= $paypal->execute($req);

    error_log("Added tracker to {$capture_id} for {$tracker->tracking_code} via {$tracker->carrier}\n");

    //error_log(json_encode($res) . "\n");

    return $res;
  }

  public function createOrder($details) {
    $client= $this->getClient();

    $request= new \PayPalCheckoutSdk\Orders\OrdersCreateRequest();
    $request->prefer('return=representation');
    $request->body= json_encode($details);

    $response= $client->execute($request);

    /* TODO error handling? */

    return $response->result;
  }

  public function getOrder($paypal_order_id) {
    $client= $this->getClient();

    $response= $client->execute(
      new \PayPalCheckoutSdk\Orders\OrdersGetRequest($paypal_order_id)
    );

    /* TODO error handling? */

    return $response->result;
  }

  public function updateOrder($paypal_order_id, $patch) {
    $client= $this->getClient();

    $request= new \PayPalCheckoutSdk\Orders\OrdersPatchRequest(
      $paypal_order_id
    );
    $request->body= json_encode($patch);

    try {
      $response= $client->execute($request);
    } catch (\PayPalHttp\HttpException $e) {
      error_log("HttpException {$e->statusCode}: {$e->result}");
      throw $e;
    }

    return $response->result;
  }
}
