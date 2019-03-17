<?php
namespace Scat;

class Image extends \Model {
  public function thumbnail() {
    return '/i/th/' . $this->uuid . '.jpg';
  }
}
