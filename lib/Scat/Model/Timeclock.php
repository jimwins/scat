<?php
namespace Scat\Model;

class Timeclock extends \Scat\Model {
  public function person() {
    return $this->belongs_to('Person')->find_one();
  }

  public function changes() {
    return $this->has_many('TimeclockAudit')->find_many();
  }
}
