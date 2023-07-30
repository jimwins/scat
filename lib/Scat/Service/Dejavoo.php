<?php
namespace Scat\Service;

use OpensslCryptor\Cryptor;

// TODO push this into class so it can get key from config service
function include_encrypted($file) {
  if (!defined('SCAT_ENCRYPTION_KEY')) return;
  $enc= file_get_contents($file);
  $dec= Cryptor::Decrypt($enc, constant('SCAT_ENCRYPTION_KEY'));
  eval('?>' . $dec);
}

include_encrypted('../lib/cc-terminal.phpc');

class Dejavoo {
  private $url;

  public function __construct(
    Config $config
  ) {
    $this->url= $config->get('dejavoo.url');
  }

  public function runTransaction($txn, $amount) {
    $cc= new \CC_Terminal($this->url);

    $label= $txn->formatted_number;

    $abs_amt= sprintf('%.02f', abs($amount));

    list($captured, $extra)=
      $cc->transaction($amount < 0 ? 'Return' : 'Sale', $abs_amt, $label);

    if ($amount < 0) {
      $captured= bcmul($captured, -1);
    }

    /*
     * We want to record the trace, but we ignore any errors because
     * we don't want to roll back the payment if it has a problem.
     */
    try {
      $trace= $this->data->factory('CcTrace')->create();
      $trace->txn_id= $txn->id;
      $trace->request= $cc->raw_request;
      $trace->response= $cc->raw_response;
      $trace->info= $cc->raw_curlinfo;
      $trace->save();
    } catch (\Exception $e) {
      error_log("Failed to capture trace: " . $e->getMessage());
    }

    return [
      'amount' => $captured,
      'data' => $extra
    ];
  }
}
