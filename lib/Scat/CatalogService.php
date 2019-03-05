<?php
namespace Scat;

class CatalogService
{
  public function __construct() {
  }

  public function getBrands($only_active= true) {
    // TODO restrict to active brands (but not in model yet)
    return \Model::factory('Brand')->order_by_asc('name')->find_many();
  }

  public function getBrandBySlug($slug) {
    return \Model::factory('Brand')->where('slug', $slug)->find_one();
  }

  public function getDepartments($parent_id= 0, $only_active= true) {
    // TODO restrict to active departments (but not in model yet)
    return \Model::factory('Department')->where('parent_id', $parent_id)
//                                      ->where_gte('active', (int)$only_active)
                                        ->order_by_asc('name')
                                        ->find_many();
  }

  public function getDepartmentBySlug($slug) {
    return \Model::factory('Department')->where('slug', $slug)->find_one();
  }

  public function GetRedirectFrom($source) {
    $dst=\Model::factory('Redirect')->where_like('source', $source)->find_one();
    return $dst;
  }
}
