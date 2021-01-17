<?php
namespace Scat\Service;

class AmazonPay {
  private $config;

  public function __construct(Config $config) {
    $this->config= [
      'public_key_id' => $config->get('amazon.public_key_id'),
      'private_key' => $config->get('amazon.private_key'),
      'region' => $config->get('amazon.region'),
      'sandbox' => (bool)$GLOBALS['DEBUG']
    ];
  }

  private function getClient() {
    $client= new \Amazon\Pay\API\Client($this->config);
    return $client;
  }

  public function refund($capture_id, $amount) {
    $amazon= $this->getClient();

    // we only have the authorization here
    $authorization= $amazon->getAuthorizationDetails([
      'amazon_authorization_id' => $charge->AmazonAuthorizationId,
    ]);
    $details= $authorization->toArray();

    if (!$amazon->success) {
      error_log("Amazon FAIL: " . json_encode($details) . "\n");
      throw new \Exception("An unexpected Amazon error occured.");
    }

    $refund= $amazon->refund([
      // XXX We assume only one capture per authorization
      'amazon_capture_id' => $details['GetAuthorizationDetailsResult']
                                      ['AuthorizationDetails']
                                      ['IdList']
                                      ['member'],
      'refund_reference_id' => uniqid(),
      'refund_amount' => $amount,
    ]);
    $details= $refund->toArray();

    if (!$amazon->success) {
      error_log("Amazon FAIL: " . json_encode($details) . "\n");
      throw new \Exception("An unexpected Amazon error occured.");
    }

    return $refund;
  }

  public function addTracker($payment, $tracker) {
    $amazon= $this->getClient();

    // we only have the authorization here
    $authorization= $amazon->getAuthorizationDetails([
      'amazon_authorization_id' => $charge->AmazonAuthorizationId,
    ]);
    $details= $authorization->toArray();

    if (!$amazon->success) {
      error_log("Amazon FAIL: " . json_encode($details) . "\n");
      throw new \Exception("An unexpected Amazon error occured.");
    }

    $refund= $amazon->refund([
      // XXX We assume only one capture per authorization
      'amazon_capture_id' => $details['GetAuthorizationDetailsResult']
                                      ['AuthorizationDetails']
                                      ['IdList']
                                      ['member'],
    $payload= array(
      'amazonOrderReferenceId' => 'P00-0000000-0000000',
      'deliveryDetails' => array(array(
          'trackingNumber' => $tracker->tracking_code,
          'carrierCode' => strtoupper($tracker->carrier),
      ))
    );

    try {
      $result= $client->deliveryTrackers($payload);
      if ($result['status'] != 200) {
        error_log("Unexpected status from Amazon: {$result['status']}, response= {$results['response']}\n");
      }
    } catch (\Exception $e) {
      error_log("Unexpected exception from Amazon: {$e->message}\n");
    }

    return $result;
  }
}
