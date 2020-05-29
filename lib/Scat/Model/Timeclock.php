<?php
namespace Scat\Model;

class Timeclock extends \Scat\Model {
  public function person() {
    return $this->belongs_to('Person');
  }

}
