<?php
namespace Scat\Service;

use OpensslCryptor\Cryptor;

class Fraud {
  public function __construct(
    private Data $data,
    private Config $config,
    private Email $email
  ) {
    $key= $config->get('fraud.key');

    // This pulls in FraudChecker class which is encrypted for reasons.
    $enc= file_get_contents('../lib/fraud-checker.phpc');
    $dec= Cryptor::Decrypt($enc, $key);
    eval('?>' . $dec);
  }

  public function checkForFraud(\Scat\Model\Cart $cart, \Scat\Service\Stripe $stripe) : void {
    $checker= new \FraudChecker();

    $action= $checker->checkForFraud($cart, $stripe);

    if ($action && $action != 'already detected') {
      $this->email->send(
        $this->email->default_from_address(),
        "Fraud detected by {$cart->email}",
        "Fraud detected for cart {$cart->uuid}\n\nAction: $action"
      );
    }
  }
}
