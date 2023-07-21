<?php
namespace Scat\Service;

class Catalog
{
  private $data;

  public function __construct(Data $data) {
    $this->data= $data;
  }

  public function createBrand() {
    return $this->data->factory('Brand')->create();
  }

  public function getBrands($only_active= true) {
    return $this->data->factory('Brand')->where_gte('brand.active',
                                               (int)$only_active)
                                   ->order_by_asc('name')
                                   ->find_many();
  }

  public function getBrandById($id) {
    return $this->data->factory('Brand')->where('id', $id)->find_one();
  }

  public function getBrandBySlug($slug) {
    return $this->data->factory('Brand')->where('slug', $slug)->find_one();
  }

  public function createDepartment() {
    return $this->data->factory('Department')->create();
  }

  public function getDepartments($parent_id= 0, $only_active= true) {
    return $this->data->factory('Department')->where('parent_id', $parent_id)
                                        ->where_gte('department.active',
                                                    (int)$only_active)
                                        ->order_by_asc('name')
                                        ->find_many();
  }

  public function getDepartmentById($id) {
    return $this->data->factory('Department')->where('id', $id)->find_one();
  }

  public function getDepartmentBySlug($slug) {
    return $this->data->factory('Department')->where('slug', $slug)->find_one();
  }

  public function createProduct() {
    return $this->data->factory('Product')->create();
  }

  public function getProductById($id) {
    return $this->data->factory('Product')->where('id', $id)->find_one();
  }

  public function getProducts($active= 1) {
    return $this->data->factory('Product')->where_gte('product.active', $active)
                                     ->find_many();
  }

  public function getNewProducts($limit= 12) {
    return $this->data->factory('Product')->where_gte('product.active', 1)
                                     ->order_by_desc('product.added')
                                     ->limit($limit)
                                     ->find_many();
  }

  public function getRedirectFrom($source) {
    // Whole product moved?
    $dst=$this->data->factory('Redirect')->where_like('source', $source)->find_one();
    // Category moved?
    if (!$dst) {
      $dst=$this->data->factory('Redirect')
             ->where_raw('? LIKE CONCAT(source, "/%")', array($source))
             ->find_one();
      if ($dst) {
        $dst->dest= preg_replace("!^({$dst->source})/!",
                                 $dst->dest . '/', $source);
      }
    }
    return $dst;
  }

  public function createItem() {
    return $this->data->factory('Item')->create();
  }

  public function getItemByCode($code) {
    return $this->data->factory('Item')
             ->where('code', $code)
             ->find_one();
  }

  public function getItemById($id) {
    return $this->data->factory('Item')->find_one($id);
  }

  public function getItems($only_active= true, $include_deleted= false) {
    return $this->data->factory('Item')
            ->where_gte('item.active', (int)$only_active)
            ->where_lte('item.deleted', (int)$include_deleted);
  }

  public function createVendorItem() {
    return $this->data->factory('VendorItem')->create();
  }

  /* VendorItem isn't unique by code, just get one */
  public function getVendorItemByCode($code) {
    return $this->data->factory('VendorItem')
             ->where('code', $code)
             ->where_gte('vendor_item.active', 1)
             ->order_by_asc('vendor_item.vendor_sku')
             ->find_one();
  }

  public function getVendorItemById($id) {
    return $this->data->factory('VendorItem')
             ->find_one($id);
  }

  public function findVendorItemsByItemIdForVendor($item_id, $vendor_id) {
    return $this->data->factory('VendorItem')
             ->where('vendor_id', $vendor_id)
             ->where('item_id', $item_id)
             ->where('active', 1)
             ->find_many();
  }

  public function findVendorItemsForVendor($vendor_id, $q) {
    $scanner= new \OE\Lukas\Parser\QueryScanner();
    $parser= new \OE\Lukas\Parser\QueryParser($scanner);
    $parser->readString($q);
    $query= $parser->parse();

    if (!$query) {
      $feedback= $parser->getFeedback();
      foreach ($feedback as $msg) {
        error_log($msg);
      }
      throw new \Exception($feedback[0]);
    }

    $v= new \Scat\VendorItemSearchVisitor();
    $query->accept($v);

    $items=
      $this->data->factory('VendorItem')
        ->select('vendor_item.*')
        ->where('vendor_item.vendor_id', $vendor_id)
        ->where_raw($v->where_clause())
        ->where_gte('vendor_item.active', $v->force_all ? 0 : 1)
        ->group_by('vendor_item.id')
        ->order_by_asc('vendor_item.vendor_sku');

    return $items;
  }

  public function getPriceOverrides() {
    return $this->data->factory('PriceOverride')
      ->select('pattern')
      ->select_expr('ANY_VALUE(`pattern_type`)', 'pattern_type')
      ->select_expr('GROUP_CONCAT(`minimum_quantity` ORDER BY `minimum_quantity`
                                  SEPARATOR ",")',
                    'breaks')
      ->select_expr('GROUP_CONCAT(`discount_type` ORDER BY `minimum_quantity`
                                  SEPARATOR ",")',
                    'discount_types')
      ->select_expr('GROUP_CONCAT(`discount` ORDER BY `minimum_quantity`
                                  SEPARATOR ",")',
                    'discounts')
      ->select_expr('GROUP_CONCAT(`in_stock` ORDER BY `minimum_quantity`
                                  SEPARATOR ",")',
                    'in_stocks')
      ->group_by('pattern')
      ->find_many();
  }
}
