<?php
namespace Scat;

class Note extends \Model {
  public function person() {
    return $this->belongs_to('Person');
  }

  public function parent() {
    return $this->belongs_to('Note', 'parent_id');
  }
}
