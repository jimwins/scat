<?php
namespace Scat;

class Note extends \Model {
  public function txn() {
    if ($this->kind == 'txn') {
      return $this->belongs_to('Txn', 'attach_id')->find_one();
    }
  }

  public function person() {
    return $this->belongs_to('Person');
  }

  public function parent() {
    return $this->belongs_to('Note', 'parent_id');
  }
}
