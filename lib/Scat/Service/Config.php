<?php
namespace Scat\Service;

class Config {
  private $_config= [];

  public function __construct(
    private \Scat\Service\Data $data
  ) {
    $entries= $data->factory('Config')->find_many();
    foreach ($entries as $entry) {
      $this->_config[$entry->name]= $entry->value;
    }
  }

  public function get($name) {
    return $this->_config[$name] ?? null;
  }

  public function set($name, $value, $type= null) {
    $row= $this->data->factory('Config')->where('name', $name)->find_one();

    if (!$row) {
      $row= $this->data->factory('Config')->create();
      $row->name= $name;
    }

    $row->value= $value;
    if ($type) {
      $row->type= $type;
    }

    $row->save();

    $this->_config[$row->name]= $row->value;
  }

  public function forget($name) {
    $row= $this->data->factory('Config')->where('name', $name)->find_one();
    if ($row) {
      $row->delete();
    }
    unset($this->_cache[$name]);
  }
}
