<?php
namespace Scat\Model;

class Config extends \Model {
  static protected $cache= [];

  static public function getValue($name) {
    if (defined($cache[$name])) {
      return $cache[$name];
    }

    $row= \Model::factory('Config')->where('name', $name)->find_one();

    if ($row) {
      return ($cache[$name]= $row->value);
    }

    return null;
  }

  static public function setValue($name, $value) {
    $row= \Model::factory('Config')->where('name', $name)->find_one();

    if (!$row) {
      $row= \Model::factory('Config')->create();
      $row->name= $name;
    }

    $row->value= $value;

    $row->save();

    unset($cache[$name]);
  }

  static public function forgetValue($name) {
    $row= \Model::factory('Config')->where('name', $name)->find_one();
    $row->delete();
    unset($cache[$name]);


    if ($row) {
      return ($cache[$name]= $row->value);
    }

    return null;
  }

}
