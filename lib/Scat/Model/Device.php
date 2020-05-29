<?php
namespace Scat\Model;

class Device extends \Scat\Model {
  public function register($token) {
    $device= self::factory('Device')->create();
    $device->token= $token;
    $device->save();

    return $device;
  }

  public function forget($token) {
    $device= self::factory('Device')->where('token', $token)->find_one();
    $device->delete();
  }

}

