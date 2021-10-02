<?php
namespace Scat\Model;

class TimeclockAudit extends \Scat\Model {
  public function timeclock() {
    return $this->belongs_to('Timeclock')->find_one();
  }
}
