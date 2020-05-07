<?php
namespace Scat;

class Model extends \Model implements \JsonSerializable {

  /* Memoize this, not sure why \Model doesn't store it on creation. */
  private $_table_name;
  public function table_name() {
    if (isset($this->_table_name)) return $this->_table_name;
    return ($this->_table_name= self::_get_table_name(get_class($this)));
  }

  public function getFields() {
    if ($this->is_new()) {
      $fields= [];
      $db= $this->orm->get_db();
      $res= $db->query("SELECT * FROM {$this->table_name()} WHERE 1=0");
      for ($i= 0; $i < $res->columnCount(); $i++) {
        $col= $res->getColumnMeta($i);
        $fields[]= $col['name'];
      }
      return $fields;
    } else {
      return array_keys($this->asArray());
    }
  }

  public function jsonSerialize() {
    return $this->asArray();
  }

  public function reload() {
    $this->orm->where_id_is($this->id);
    $this->orm->limit(1);
    $rows= $this->orm->_run();

    if (empty($rows)) {
      return false;
    }

    $this->orm->hydrate($rows[0]);
    return $this;
  }

  /* Reload new things so we get all the fields with defaults. */
  public function save() {
    $new= $this->is_new();
    parent::save();
    return $new ? $this->reload() : $this;
  }
}
