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
    $fm= in_array(strtolower($this->ext), [ 'tif', 'tiff' ]) ? 'jpg' : 'auto';
    $config= $GLOBALS['container']->get(\Scat\Service\Config::class);
    return $config->get('gumlet.base_url') .
           $this->uuid . '.' . $this->ext .
           '?w=1024&h=1024&mode=fill&fill=solid&fill-color=white&fm=' . $fm;
  }

  public function at_size($width, $height= null) {
    if ($height === null) {
      if ($this->width) {
        $height= (int)($this->height / $this->width * $width);
      } else {
        $height= $width;
      }
    }
    $fm= in_array(strtolower($this->ext), [ 'tif', 'tiff' ]) ? 'jpg' : 'auto';
    $config= $GLOBALS['container']->get(\Scat\Service\Config::class);
    return $config->get('gumlet.base_url') .
           $this->uuid . '.' . $this->ext .
           '?w=' . $width . '&h=' . $height . '&mode=fit&fm=' . $fm;
  }

  public function productsUsedBy() {
    return $this->has_many_through('Product');
  }

  public function itemsUsedBy() {
    return $this->has_many_through('Item');
  }

  public function as_array() {
    $data= parent::as_array();
    $data['thumbnail']= $this->thumbnail();
    $data['medium']= $this->medium();
    $data['large_square']= $this->large_square();
    return $data;
  }
}
