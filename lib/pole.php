<?php

function pole_display_price($label, $price) {
  $sock= socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
  if (@socket_connect($sock, '127.0.0.1', 1888)) {
    socket_write($sock,
                 sprintf("\x0d\x0a%-19.19s\x0a\x0d$%18.2f ",
                         $label, $price));
  }
}
