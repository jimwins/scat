<?php
namespace Scat\Model;

class WebPushSubscription extends \Scat\Model {
  public static function register($endpoint, $keys) {
    $sub= self::factory('WebPushSubscription')->create();
    $sub->endpoint= $endpoint;
    $sub->data= json_encode($keys);
    $sub->save();

    return $sub;
  }

  public static function forget($endpoint) {
    $sub= self::factory('WebPushSubscription')->where('endpoint', $endpoint)->find_one();
    if ($sub) $sub->delete();
  }
}


