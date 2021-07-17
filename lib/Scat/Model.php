<?php
namespace Scat;

class Model extends \Titi\Model implements \JsonSerializable {

  /* Memoize this, not sure why \Titi\Model doesn't store it on creation. */
  private $_table_name;
  public function table_name() {
    if (isset($this->_table_name)) return $this->_table_name;
    return ($this->_table_name= self::_get_table_name(get_class($this)));
  }

  public function getFields() {
    if ($this->is_new()) {
      $fields= [];
      $db= $this->orm->get_db();
      $res= $db->query("SELECT * FROM {$this->table_name()} WHERE 1=0");
      for ($i= 0; $i < $res->columnCount(); $i++) {
        $col= $res->getColumnMeta($i);
        $fields[]= $col['name'];
      }
      return $fields;
    } else {
      return array_keys($this->asArray());
    }
  }

  public function jsonSerialize() {
    return $this->asArray();
  }

  public function reload() {
    // punt if we don't have an id
    if (!$this->id) return $this;

    $this->orm->where_id_is($this->id);
    $this->orm->limit(1);
    $rows= $this->orm->_run();

    if (empty($rows)) {
      return false;
    }

    $this->orm->hydrate($rows[0]);
    return $this;
  }

  /* Reload new things so we get all the fields with defaults. */
  public function save() {
    $new= $this->is_new();
    parent::save();
    return $new ? $this->reload() : $this;
  }

  /*
   * Maybe not the right place to have this, but convenient.
   */
  public function calcSalePrice($retail_price, $discount_type, $discount) {
    switch ($discount_type) {
    case 'percentage':
      $retail_price= new \Decimal\Decimal($retail_price);
      $discount= 100 - new \Decimal\Decimal($discount);
      $sale_price= $retail_price * ($discount / 100);
      return (string)$sale_price->round(2, \Decimal\Decimal::ROUND_HALF_EVEN);
    case 'relative':
      $price= new \Decimal\Decimal($this->retail_price) -
              new \Decimal\Decimal($this->discount);
      return (string)$price->round(2, \Decimal\Decimal::ROUND_HALF_EVEN);
    case 'fixed':
      return $discount;
    case '':
    case null:
      return $retail_price;
    default:
      throw new \Exception("Did not understand discount type ('$discount_type') for item.");
    }
  }
}
