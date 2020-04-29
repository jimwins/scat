<?php
namespace Scat\Service;

class Config
{
  public function get($name) {
    return \Scat\Model\Config::getValue($name);
  }

  public function set($name, $value, $type= null) {
    return \Scat\Model\Config::setValue($name, $value, $type);
  }

  public function forget($name) {
    return \Scat\Model\Config::forgetValue($name, $value);
  }
}
