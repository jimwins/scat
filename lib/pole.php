<?php

function pole_display_price($label, $price) {
  if ($GLOBALS['DEBUG']) {
    error_log(sprintf("POLE: %-19.19s - $%18.2f", $label, $price));
  }

  if (!defined('POLE_SERVER')) {
    error_log("POLE_SERVER not configured");
    return;
  }

  $sock= @fsockopen(POLE_SERVER, 1888, $errno, $errstr, 1);
  if ($sock) {
    fwrite($sock, sprintf("\x0a\x0d%-19.19s\x0a\x0d$%18.2f ", $label, $price));
  }
}
