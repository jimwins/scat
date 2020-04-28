<?php
namespace Scat\Service;

class Config
{
  public function get($name) {
    return \Scat\Model\Config::getValue($name);
  }

  public function set($name, $value) {
    return \Scat\Model\Config::setValue($name, $value);
  }
}
