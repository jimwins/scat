<?php
namespace Scat\Service;

require '../extern/cryptor.php';

// TODO push this into class so it can get key from config service
function include_encrypted($file) {
  $enc= file_get_contents($file);
  $dec= \Cryptor::Decrypt($enc, SCAT_ENCRYPTION_KEY);
  eval('?>' . $dec);
}

include_encrypted('../lib/cc-terminal.phpc');

class Dejavoo {
  private $data, $config;

  public function __construct(Data $data, Config $config) {
    $this->data= $data;
    $this->config= $config;
  }

  public function runTransaction($txn, $amount) {
    $cc= new \CC_Terminal();

    $label= $txn->formatted_number;

    list($captured, $extra)=
      $cc->transaction($amount < 0 ? 'Return' : 'Sale', abs($amount), $label);

    if ($amount < 0) {
      $captured= bcmul($captured, -1);
    }

    $trace= $data->factory('CcTrace')->create();
    $trace->txn_id= $txn->id;
    $trace->raw_request= $cc->raw_request;
    $trace->raw_response= $cc->raw_response;
    $trace->raw_curlinfo= $cc->raw_curlinfo;
    $trace->save();

    return [
      'amount' => $captured,
      'data' => $extra
    ];
  }
}
