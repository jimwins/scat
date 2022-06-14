<?php
namespace Scat\Service;

class AmazonPay {
  private $config;
  private $merchant_id, $client_id;

  public function __construct(Config $config) {
    $this->config= [
      'public_key_id' => $config->get('amazon.public_key_id'),
      'private_key' => $config->get('amazon.private_key'),
      'region' => $config->get('amazon.region'),
      'sandbox' => (bool)$GLOBALS['DEBUG']
    ];
    $this->merchant_id= $config->get('amazon.merchant_id');
    $this->client_id= $config->get('amazon.client_id');
  }

  private function getClient() {
    $client= new \Amazon\Pay\API\Client($this->config);
    return $client;
  }

  public function getEnvironment($link) {
    if (!$this->merchant_id) return null;

    $client= $this->getClient();

    $payload= [
      'webCheckoutDetails' => [
        'checkoutReviewReturnUrl' => $link,
      ],
      'storeId' => $this->client_id,
      'deliverySpecifications' => [
        'addressRestrictions' => [
          'type' => 'Allowed',
          'restrictions' => [
            'US' => [
              'zipCodes' => [ '*' ],
            ],
          ],
        ],
      ],
    ];

    $json_payload= json_encode($payload);

    $signature= $client->generateButtonSignature($json_payload);

    return [
      'merchant_id' => $this->merchant_id,
      'public_key_id' => $this->config['public_key_id'],
      'payload' => $json_payload,
      'signature' => $signature,
    ];
  }

  public function refund($charge_id, $amount, $uuid) {
    $client= $this->getClient();

    $payload= [
      'chargeId' => $charge_id,
      'refundAmount' => [
        'amount' => $amount,
        'currencyCode' => 'USD',
      ],
      'softDescriptor' => 'Refund',
    ];

    return $this->handleResult(
      $client->createRefund($payload, [
        'x-amz-pay-idempotency-key' => $uuid,
      ])
    );
  }

  public function addTracker($payment, $tracker) {
    $client= $this->getClient();

    $charge_permission_id= $payment->data()->chargePermissionId;

    /* Force carrier to UPS if it was UPSDAP */
    $carrier= strtoupper($tracker->carrier);
    if ($carrier == 'UPSDAP') $carrier= 'UPS';

    $payload= [
      'chargePermissionId' => $charge_permission_id,
      'deliveryDetails' => [
        [
          'trackingNumber' => $tracker->tracking_code,
          'carrierCode' => $carrier,
        ]
      ],
    ];

    error_log("Amazon: Adding tracker to {$charge_permission_id}");

    return $this->handleResult(
      $client->deliveryTrackers($payload)
    );
  }

  private function handleResult($result) {
    $data= json_decode($result['response']);

    if ($result['status'] != 200 && $result['status'] != 201) {
      throw new \Exception("{$data->reasonCode}: $data->message}");
    }

    if (json_last_error() != JSON_ERROR_NONE) {
      throw new \Exception(json_last_error_msg());
    }

    return $data;
  }

  public function getCheckoutSession($amzn_session_id) {
    $client= $this->getClient();
    return $this->handleResult(
      $client->getCheckoutSession($amzn_session_id)
    );
  }

  public function updateCheckoutSession($amzn_session_id, $data) {
    $client= $this->getClient();
    return $this->handleResult(
      $client->updateCheckoutSession($amzn_session_id, $data)
    );
  }

  public function completeCheckoutSession($amzn_session_id, $data) {
    $client= $this->getClient();
    return $this->handleResult(
      $client->completeCheckoutSession($amzn_session_id, $data)
    );
  }

  public function capture($charge_id, $amount, $uuid) {
    $client= $this->getClient();

    $payload= [
      'captureAmount' => [
        'amount' => $amount,
        'currencyCode' => 'USD',
      ],
    ];

    return $this->handleResult(
      $client->captureCharge($charge_id, $payload, [
        'x-amz-pay-idempotency-key' => $uuid,
      ])
    );
  }
}
