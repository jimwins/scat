<?php

class Brand extends Model {
  public function products() {
    return $this->has_many('Product');
  }
}

class Department extends Model {
  public function parent() {
    return $this->belongs_to('Department', 'parent_id');
  }

  public function departments() {
    return $this->has_many('Department', 'parent_id');
  }

  public function products() {
    return $this->has_many('Product');
  }
}

class Product extends Model {
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
}

class Item extends Model {
  /* XXX Legacy, should get from parent product */
  public function brand() {
    return $this->belongs_to('Brand', 'brand');
  }

  public function product() {
    return $this->belongs_to('Product');
  }

  public function barcodes() {
    return $this->has_many('Barcode', 'item');
  }
}

class Barcode extends Model {
  public function item() {
    return $this->belongs_to('Item', 'item');
  }
}

class Note extends Model {
  public function person() {
    return $this->belongs_to('Person');
  }

  public function parent() {
    return $this->belongs_to('Note', 'parent_id');
  }
}
