<?php
/* Just a dummy for static analysis. */

class CC_Terminal {
  public $raw_request;
  public $raw_response;
  public $raw_curlinfo;

  public function transaction($type, $amount, $invoice, $register = 1) {
  }

  public function void($refid, $amount, $invoice, $register = 1) {
  }

  public function settleBatch($register= 1) {
  }
}
