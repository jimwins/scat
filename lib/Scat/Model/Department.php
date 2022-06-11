<?php
namespace Scat\Model;

class Department extends \Scat\Model {
  private $old_slug;

  public function full_slug() {
    return
      ($this->parent_id ? $this->parent()->slug . '/' : '') .
      $this->slug;
  }

  public function url_params() {
    if ($this->parent_id) {
      return [
        'dept' => $this->parent()->slug,
        'subdept' => $this->slug,
      ];
    } else {
      return [ 'dept' => $this->slug ];
    }
  }

  public function parent() {
    return $this->belongs_to('Department', 'parent_id')->find_one();
  }

  public function departments($only_active= true) {
    return $this->has_many('Department', 'parent_id')
                ->where_gte('department.active', (int)$only_active);
  }

  public function products($only_active= true) {
    return $this->has_many('Product')
                ->where_gte('product.active', (int)$only_active);
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
        ($this->is_dirty('slug') || $this->is_dirty('parent_id'))) {
      $new_slug= $this->full_slug();
      error_log("Redirecting {$this->old_slug} to $new_slug");
      $redir= self::factory('Redirect')->create();
      $redir->source= $this->old_slug;
      $redir->dest= $new_slug;
      $redir->save();
    }
    parent::save();
  }

  public function image() {
    if ($this->parent_id) {
      $depts= [ $this ];
    } else {
      $depts= $this->departments()
                    ->order_by_expr('RAND(TO_DAYS(NOW()))')
                    ->find_many();
    }

    foreach ($depts as $dept) {
      $products= $dept->products()
                      ->order_by_expr('RAND(TO_DAYS(NOW()))')
                      ->find_many();
      foreach ($products as $product) {
        if ($product->items()->count() > 0) {
          $media= $product->media();
          if ($media) {
            return $media[0];
          }
        }
      }
    }

    return false;
  }
}
