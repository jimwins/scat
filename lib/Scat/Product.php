<?php
namespace Scat;

class Product extends \Model implements \JsonSerializable {
  public function brand() {
    return $this->belongs_to('Brand');
  }

  public function items() {
    return $this->has_many('Item')
                ->select('item.*')
                ->select_expr('sale_price(item.retail_price,
                                          item.discount_type,
                                          item.discount)',
                              'sale_price')
                ->select_expr('(SELECT IFNULL(SUM(allocated),0)
                                  FROM txn_line
                                 WHERE txn_line.item = item.id)',
                              'stock');
  }

  public function jsonSerialize() {
    return $this->asArray();
  }
}
