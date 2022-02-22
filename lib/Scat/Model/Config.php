<?php
namespace Scat\Model;

class Config extends \Scat\Model {
  static protected $cache= [];

  static public function getValue($name) {
    if (array_key_exists($name, self::$cache)) {
      return self::$cache[$name];
    }

    $row= self::factory('Config')->where('name', $name)->find_one();

    if ($row) {
      return (self::$cache[$name]= $row->value);
    }

    return null;
  }

  static public function setValue($name, $value, $type= null) {
    $row= self::factory('Config')->where('name', $name)->find_one();

    if (!$row) {
      $row= self::factory('Config')->create();
      $row->name= $name;
    }

    $row->value= $value;
    if ($type) {
      $row->type= $type;
    }

    $row->save();

    unset(self::$cache[$name]);
  }

  static public function forgetValue($name) {
    $row= self::factory('Config')->where('name', $name)->find_one();
    if ($row) {
      $row->delete();
    }
    unset(self::$cache[$name]);


    if ($row) {
      return (self::$cache[$name]= $row->value);
    }

    return null;
  }

}
