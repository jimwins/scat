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
    return \Model::factory('Brand')->where_gte('brand.active',
                                               (int)$only_active)
                                   ->order_by_asc('name')
                                   ->find_many();
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
    return \Model::factory('Department')->where('parent_id', $parent_id)
                                        ->where_gte('department.active',
                                                    (int)$only_active)
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

  public function getProducts($active= 1) {
    return \Model::factory('Product')->where_gte('product.active', $active)
                                     ->find_many();
  }

  public function getNewProducts($limit= 12) {
    return \Model::factory('Product')->where_gte('product.active', 1)
                                     ->order_by_desc('product.added')
                                     ->limit($limit)
                                     ->find_many();
  }

  public function GetRedirectFrom($source) {
    // Whole product moved?
    $dst=\Model::factory('Redirect')->where_like('source', $source)->find_one();
    // Category moved?
    if (!$dst) {
      $dst=\Model::factory('Redirect')
             ->where_raw('? LIKE CONCAT(source, "/%")', array($source))
             ->find_one();
      if ($dst) {
        $dst->dest= preg_replace("!^({$dst->source})/!",
                                 $dst->dest . '/', $source);
      }
    }
    return $dst;
  }
}
