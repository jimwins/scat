<?php
namespace Scat\Service;

class PoleDisplay
{
  private $host, $port;

  public function __construct(
    Config $config
  ) {
    $this->host= $config->get('poledisplay.host');
    $this->port= $config->get('poledisplay.port') ?: 1888;
  }

  function displayPrice($label, $price) {
    if ($GLOBALS['DEBUG']) {
      error_log(sprintf("POLE: %-19.19s - $%18.2f", $label, $price));
    }

    if (!$this->host) {
      error_log("No pole display configured");
      return;
    }

    $sock= @fsockopen($this->host, $this->post, $errno, $errstr, 1);
    if ($sock) {
      fwrite($sock, sprintf("\x0a\x0d%-19.19s\x0a\x0d$%18.2f ", $label, $price));
    }
  }
}
