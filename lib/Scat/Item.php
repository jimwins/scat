<?
namespace Scat;

class Item extends \Model implements \JsonSerializable {
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

  public function full_slug() {
    $product= $this->product()->find_one();
    if ($product)
      $subdept= $product->dept()->find_one();
    if ($subdept)
      $dept= $subdept->parent()->find_one();
    if (!$product || !$subdept || !$dept)
      return "";

    return
      $dept->slug . '/' .
      $subdept->slug . '/' .
      $product->slug . '/' .
      $this->code;
  }

  public function sale_price() {
    switch ($this->discount_type) {
    case 'percentage':
      // TODO fix rounding
      return bcmul($this->retail_price,
                   bcdiv(bcsub(100, $this->discount),
                         100));
    case 'relative':
      return bcsub($this->retail_price, $this->discount);
    case 'fixed':
      return $this->discount;
    case '':
    case null:
      return $this->retail_price;
    default:
      throw new Exception('Did not understand discount for item.');
    }
  }

  public function stock() {
    return $this->has_many('TxnLine', 'item')
                ->sum('allocated') ?: 0;
  }

  public function prop65_warning() {
    return $this->belongs_to('Prop65_Warning', 'prop65')->find_one();
  }

  public function recent_sales($days= 90) {
    $days= (int)$days; // Make sure we have an integer
    $q= "SELECT SUM(-1 * allocated) AS sold
           FROM txn
           JOIN txn_line ON txn.id = txn_line.txn
          WHERE type = 'customer'
            AND item = {$this->id}
            AND created BETWEEN NOW() - INTERVAL $days DAY AND NOW()";

    $res= \ORM::for_table('txn')->raw_query($q)->find_one();

    error_log(json_encode($res));
    return $res->sold;
  }

  public function setProperty($name, $value) {
    switch ($name) {
      case 'retail_price':
        $value= preg_replace('/^\\$/', '', $value);
        // passthrough
      case 'code':
      case 'name':
      case 'short_name':
      case 'variation':
      case 'tic':
      case 'color':
      case 'active':
      case 'product_id':
      case 'purchase_quantity':
      case 'minimum_quantity':
      case 'prop65':
      case 'hazmat':
      case 'oversized':
      case 'length':
      case 'width':
      case 'height':
        $this->$name= $value;
        break;
      case 'discount':
        $this->setDiscount($value);
        break;
      case 'stock':
        $this->setStock($value);
        break;
      case 'dimensions':
        $this->setDimensions($value);
        break;
      default:
        throw \Exception("No way to set '$name' on an item.");
    }
  }

  public function setDiscount($discount) {
    $discount= preg_replace('/^\\$/', '', $discount);
    if (preg_match('/^(\d*)(\/|%)( off)?$/', $discount, $m)) {
      $discount = (float)$m[1];
      $discount_type = "percentage";
    } elseif (preg_match('/^(\d*\.?\d*)$/', $discount, $m)) {
      $discount = (float)$m[1];
      $discount_type = "fixed";
    } elseif (preg_match('/^\$?(\d*\.?\d*)( off)?$/', $discount, $m)) {
      $discount = (float)$m[1];
      $discount_type = "relative";
    } elseif (preg_match('/^-\$?(\d*\.?\d*)$/', $discount, $m)) {
      $discount = (float)$m[1];
      $discount_type = "relative";
    } elseif (preg_match('/^(def|\.\.\.)$/', $discount)) {
      $discount= null;
      $discount_type= null;
    } else {
      throw \Exception("Did not understand discount.");
    }
    $this->discount= $discount;
    $this->discount_type= $discount_type;
  }

  public function setStock($stock) {
    $current= $this->stock();
    if ($stock != $current) {
      $cxn= \Model::factory('Txn')
        ->where_raw("type = 'correction' AND DATE(NOW()) = DATE(created)")
        ->find_one();
      if (!$cxn) {
        $cxn= \Scat\Txn::create([ 'type' => 'correction', 'tax_rate' => 0 ]);
      }

      $diff= $stock - $current;

      // TODO should have a \Scat\Item method for this
      $q= "SELECT retail_price AS cost
             FROM txn_line
             JOIN txn ON (txn_line.txn = txn.id)
            WHERE item = {$this->id} AND type = 'vendor'
            ORDER BY created DESC
            LIMIT 1";
      $res= \ORM::for_table('txn_line')->raw_query($q)->find_one();
      $cost= $res ? $res->cost : 0.00;

      $txn_line= \Model::factory('TxnLine')
        ->where_equal('txn', $cxn->id)
        ->where_equal('item', $this->id)
        ->find_one();

      if ($txn_line) {
        $txn_line->ordered+= $diff;
        $txn_line->allocated+= $diff;
        $txn_line->retail_price= $txn_line->ordered < 0 ? $cost : 0.00;
      } else {
        $txn_line= \Model::factory('TxnLine')->create();
        $txn_line->txn= $cxn->id;
        $txn_line->item= $this->id;
        $txn_line->ordered= $diff;
        $txn_line->allocated= $diff;
        $txn_line->retail_price= $cost;
      }
      $txn_line->save();
    }
  }

  public function setDimensions($dimensions) {
    list($l, $w, $h)= preg_split('/\s*x\s*/', $dimensions);
    $this->length= $l;
    $this->width= $w;
    $this->height= $h;
  }

  public function jsonSerialize() {
    return $this->asArray();
  }
}

class Barcode extends \Model {
  public function item() {
    return $this->belongs_to('Item', 'item');
  }
}

class Prop65_Warning extends \Model {
}
