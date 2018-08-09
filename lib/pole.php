<?php

function pole_display_price($label, $price) {
  if (!function_exists('socket_create')) return;

  $sock= socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
  /* Set 1 sec timeout to avoid getting stuck, should be plenty long enough */
  socket_set_option($sock, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 1,
                                                          'usec' => 0));
  if (@socket_connect($sock, '127.0.0.1', 1888)) {
    socket_write($sock,
                 sprintf("\x0a\x0d%-19.19s\x0a\x0d$%18.2f ",
                         $label, $price));
  }
}
