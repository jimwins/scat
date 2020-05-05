<?php
namespace Scat\Model;

class Product extends \Scat\Model {
  private $old_slug;

  public function brand() {
    return $this->belongs_to('Brand')->find_one();
  }

  public function brand_name() {
    return $this->brand()->name;
  }

  public function dept() {
    return $this->belongs_to('Department')->find_one();
  }

  public function items($only_active= true) {
    return $this->has_many('Item')
                ->select('item.*')
                ->where_gte('item.active', (int)$only_active);
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

  public function jsonSerialize() {
    return array_merge($this->asArray(), [
      'full_slug' => $this->full_slug()
    ]);
  }

  public function media() {
    $media= $this->has_many_through('Image')->find_many();
    if (!$media[0]->id) {
      return $this->image ?
        [
          [
            'src' => $this->image,
            'thumbnail' => ORDURE_STATIC . $this->image,
            'alt_text' => $this->name
          ]
        ] : null;
    }
    return $media;
  }

  public function addImage($image) {
    $rel= \Model::factory('ImageProduct')->create();
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
        $redir= \Model::factory('Redirect')->create();
        $redir->source= $this->old_slug;
        $redir->dest= $new_slug;
        $redir->save();
      }
    }
    parent::save();
  }
}
