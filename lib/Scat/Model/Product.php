<?php
namespace Scat\Model;

class Product extends \Scat\Model {
  private $old_slug;
  private $_cache= [];

  public function brand() {
    return @$this->_cache['brand'] ?:
      ($this->_cache['brand']= $this->belongs_to('Brand')->find_one());
  }

  public function brand_name() {
    return $this->brand()->name;
  }

  public function dept() {
    return @$this->_cache['dept'] ?:
      ($this->_cache['dept']= $this->belongs_to('Department')->find_one());
  }

  public function items($only_active= true) {
    return $this->has_many('Item')
                ->select('item.*')
                ->where_gte('item.active', (int)$only_active);
  }

  public function url_params() {
    return [
      'dept' => $this->dept()->parent()->slug,
      'subdept' => $this->dept()->slug,
      'product' => $this->slug
    ];
  }

  public function full_slug() {
    return
      $this->dept()->parent()->slug . '/' .
      $this->dept()->slug . '/' . $this->slug;
  }

  public function stocked() {
    return $this->has_many('Item')
                ->where_gte('item.active', 1)
                ->where_gte('minimum_quantity', 1)
                ->count();
  }

  public function is_in_warehouse() {
    foreach ($this->items()->find_many() as $item) {
      if (!$item->is_in_warehouse()) {
        return false;
      }
    }
    return true;
  }


  public function last_inventoried() {
    return $this->has_many('Item')
                ->where_gte('item.active', 1)
                ->max('inventoried');
  }

  public function jsonSerialize() {
    return array_merge($this->asArray(), [
      'full_slug' => $this->full_slug()
    ]);
  }

  public function media_link() {
    return $this->has_many('ProductToImage');
  }

  public function has_media() {
    return $this->has_many_through('Image')->count();
  }

  public function media() {
    $media= $this->has_many_through('Image')->find_many();
    if (!count($media) && $this->image) {
      $dummy= new DummyMedia($this->image, $this->name);
      return [ $dummy ];
    }
    return $media;
  }

  public function addImage($image) {
    $rel= self::factory('ImageProduct')->create();
    $rel->image_id= $image->id;
    $rel->product_id= $this->id;
    $rel->save();
  }

  // XXX A gross hack to find when slug changes.
  function set_orm($orm) {
    parent::set_orm($orm);
    if ($this->id) {
      $this->old_slug= $this->full_slug();
    }
  }

  function save() {
    if ($this->id &&
        ($this->is_dirty('slug') || $this->is_dirty('department_id'))) {
      $new_slug= $this->full_slug();
      if ($new_slug != $this->old_slug) {
        error_log("Redirecting {$this->old_slug} to $new_slug");
        $redir= self::factory('Redirect')->create();
        $redir->source= $this->old_slug;
        $redir->dest= $new_slug;
        $redir->save();
      }
    }
    parent::save();
  }
}

class ProductToImage extends \Scat\Model {
  function delete() {
    $this->orm->use_id_column([ 'product_id', 'image_id' ]);
    return parent::delete();
  }
}

/*
 * TODO: remove this
 * Artifact of our old product catalog data
 */
class DummyMedia {
  private $image;
  private $static;

  function __construct($image) {
    $this->image= $image;
    $config= $GLOBALS['container']->get(\Scat\Service\Config::class);
    $this->static= $config->get('ordure.static_url');
  }

  function original() {
    return $this->static . $this->image;
  }

  function medium() {
    return $this->static . $this->image;
  }

  function thumbnail() {
    return $this->static . $this->image;
  }

  function large_square() {
    return $this->static . $this->image;
  }
}
