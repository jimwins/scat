<?php
namespace Scat\Model;

class Image extends \Scat\Model {
  public function original() {
    $config= $GLOBALS['container']->get(\Scat\Service\Config::class);
    return $config->get('gumlet.base_url') .
           $this->uuid . '.' . $this->ext;
  }

  public function thumbnail() {
    $config= $GLOBALS['container']->get(\Scat\Service\Config::class);
    return $config->get('gumlet.base_url') .
           $this->uuid . '.' . $this->ext .
           '?w=128&h=128&mode=fit&fm=auto';
  }

  public function medium() {
    $config= $GLOBALS['container']->get(\Scat\Service\Config::class);
    return $config->get('gumlet.base_url') .
           $this->uuid . '.' . $this->ext .
           '?w=384&h=384&mode=fit&fm=auto';
  }

  public function large_square() {
    $fm= in_array(strtolower($this->ext), [ 'tif', 'tiff' ]) ? 'jpeg' : 'auto';
    $config= $GLOBALS['container']->get(\Scat\Service\Config::class);
    return $config->get('gumlet.base_url') .
           $this->uuid . '.' . $this->ext .
           '?w=1024&h=1024&mode=fill&fill=solid&fill-color=white&fm=' . $fm;
  }

  public function productsUsedBy() {
    return $this->has_many_through('Product');
  }

  public function itemsUsedBy() {
    return $this->has_many_through('Item');
  }
}
