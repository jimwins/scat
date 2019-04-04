<?php
namespace Scat;

class CatalogService
{
  public function __construct() {
  }

  public function createBrand() {
    return \Model::factory('Brand')->create();
  }

  public function getBrands($only_active= true) {
    // TODO restrict to active brands (but not in model yet)
    return \Model::factory('Brand')->order_by_asc('name')->find_many();
  }

  public function getBrandById($id) {
    return \Model::factory('Brand')->where('id', $id)->find_one();
  }

  public function getBrandBySlug($slug) {
    return \Model::factory('Brand')->where('slug', $slug)->find_one();
  }

  public function createDepartment() {
    return \Model::factory('Department')->create();
  }

  public function getDepartments($parent_id= 0, $only_active= true) {
    // TODO restrict to active departments (but not in model yet)
    return \Model::factory('Department')->where('parent_id', $parent_id)
//                                      ->where_gte('active', (int)$only_active)
                                        ->order_by_asc('name')
                                        ->find_many();
  }

  public function getDepartmentById($id) {
    return \Model::factory('Department')->where('id', $id)->find_one();
  }

  public function getDepartmentBySlug($slug) {
    return \Model::factory('Department')->where('slug', $slug)->find_one();
  }

  public function createProduct() {
    return \Model::factory('Product')->create();
  }

  public function getProductById($id) {
    return \Model::factory('Product')->where('id', $id)->find_one();
  }

  public function GetRedirectFrom($source) {
    $dst=\Model::factory('Redirect')->where_like('source', $source)->find_one();
    return $dst;
  }
}
