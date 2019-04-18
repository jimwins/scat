<?php
namespace Scat;

class Department extends \Model implements \JsonSerializable {
  private $old_slug;

  public function full_slug() {
    return
      ($this->parent_id ? $this->parent()->find_one()->slug . '/' : '') .
      $this->slug;
  }

  public function parent() {
    return $this->belongs_to('Department', 'parent_id');
  }

  public function departments() {
    return $this->has_many('Department', 'parent_id');
  }

  public function products($only_active= true) {
    return $this->has_many('Product')->where_gte('active', (int)$only_active);
  }

  public function jsonSerialize() {
    $array= $this->as_array();
    return $array;
  }

  // XXX A gross hack to find when slug changes.
  function set_orm($orm) {
    parent::set_orm($orm);
    $this->old_slug= $this->full_slug();
  }

  function save() {
    if ($this->is_dirty('slug') || $this->is_dirty('parent_id')) {
      $new_slug= $this->full_slug();
      error_log("Redirecting {$this->old_slug} to $new_slug");
      $redir= \Model::factory('Redirect')->create();
      $redir->source= $this->old_slug;
      $redir->dest= $new_slug;
      $redir->save();
    }
    parent::save();
  }
}
