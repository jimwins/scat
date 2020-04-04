<?php
namespace Scat;

class Device extends \Model implements \JsonSerializable {
  public function register($token) {
    $device= \Model::factory('Device')->create();
    $device->token= $token;
    $device->save();

    return $device;
  }

  public function forget($token) {
    $device= \Model::factory('Device')->where('token', $token)->find_one();
    $device->delete();
  }

  public function jsonSerialize() {
    return $this->as_array();
  }
}

