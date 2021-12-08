<?
namespace Scat\Model;

class Item extends \Scat\Model {
  /* XXX Legacy, should get from parent product */
  public function brand() {
    return $this->belongs_to('Brand', 'brand')->find_one();
  }

  public function product() {
    return $this->belongs_to('Product')->find_one();
  }

  public function barcodes() {
    return $this->has_many('Barcode');
  }

  public function barcode() {
    $barcodes= $this->barcodes()->find_many();
    if (!$barcodes) {
      return $this->fake_barcode();
    }

    foreach ($barcodes as $barcode) {
      if ($barcode->quantity == 1) {
        return $barcode->code;
      }
    }

    return $barcodes[0]->code;
  }

  private function generate_upc($code) {
    assert(strlen($code) == 11);
    $check= 0;
    foreach (range(0,10) as $digit) {
      $check+= $code[$digit] * (($digit % 2) ? 1 : 3);
    }

    $cd= 10 - ($check % 10);
    if ($cd == 10) $cd= 0;

    return $code.$cd;
  }

  public function fake_barcode() {
    return $this->generate_upc(sprintf("4004%07d", $this->id));
  }

  public function in_kits() {
    return $this->has_many_through('Item', 'KitItem', null, 'kit_id', null, 'id')
      ->find_many();
  }

  public function vendor_items($active= 1) {
    return $this->has_many('VendorItem')->where_gte('active', $active);
  }

  public function vendor_item($person_id) {
    $item= $this->has_many('VendorItem')
                ->where_gte('active', $active)
                ->where('vendor_id', $person_id)
                ->find_one();
    return $item;
  }

  public function vendor_sku($person_id) {
    $item= $this->vendor_item($person_id);
    return $item ? $item->vendor_sku : null;
  }

  public function most_recent_cost() {
    $q= "SELECT retail_price AS cost
           FROM txn_line
           JOIN txn ON (txn_line.txn_id = txn.id)
          WHERE item_id = {$this->id} AND type = 'vendor'
          ORDER BY created DESC
          LIMIT 1";
    $res= $this->orm->for_table('txn_line')->raw_query($q)->find_one();

    return $res ? $res->cost : null;
  }

  public function best_cost() {
    $q= "SELECT MIN(IF(promo_price && promo_price < net_price,
                        promo_price, net_price)) AS cost
           FROM vendor_item
          WHERE item_id = {$this->id}
            AND active";
    $res= $this->orm->for_table('vendor_item')->raw_query($q)->find_one();

    return $res ? $res->cost : null;
  }

  public function usual_cost() {
    $q= "SELECT MIN(net_price) AS cost
           FROM vendor_item
          WHERE item_id = {$this->id}
            AND active";
    $res= $this->orm->for_table('vendor_item')->raw_query($q)->find_one();

    return $res ? $res->cost : null;
  }

  public function kit_items() {
    if ($this->is_kit) {
      return $this->has_many('KitItem', 'kit_id')
        ->order_by_asc('sort');
    }
  }

  public function media() {
    return $this->has_many_through('Image')
      ->order_by_asc('priority')
      ->find_many();
  }

  public function media_link() {
    return $this->has_many('ItemToImage');
  }

  public function addImage($image) {
    $rel= self::factory('ImageItem')->create();
    $rel->image_id= $image->id;
    $rel->item_id= $this->id;
    $rel->save();
  }

  public function full_slug() {
    $product= $this->product();
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
    return $this->calcSalePrice(
      $this->retail_price,
      $this->discount_type,
      $this->discount
    );
  }

  public function stock() {
    return $this->has_many('TxnLine')
                ->sum('allocated') ?: 0;
  }

  public function on_order() {
    return $this->has_many('TxnLine')
                ->join('txn', [ 'txn.id', '=', 'txn_line.txn_id' ])
                ->where_equal('txn.type', 'vendor')
                ->where_raw('ordered != allocated')
                ->sum('ordered') ?: 0;
  }

  public function prop65_warning() {
    return $this->belongs_to('Prop65_Warning', 'prop65')->find_one();
  }

  public function recent_sales($days= 90) {
    $days= (int)$days; // Make sure we have an integer
    $q= "SELECT SUM(-1 * allocated) AS units,
                SUM(-1 * allocated *
                    sale_price(retail_price, discount_type, discount)) AS gross
           FROM txn
           JOIN txn_line ON txn.id = txn_line.txn_id
          WHERE type = 'customer'
            AND item_id = {$this->id}
            AND created BETWEEN NOW() - INTERVAL $days DAY AND NOW()";

    $res= $this->orm->for_table('txn')->raw_query($q)->find_one();

    return $res;
  }

  public function setProperty($name, $value) {
    switch ($name) {
      case 'dropship_fee':
      case 'retail_price':
        $value= preg_replace('/^\\$/', '', $value);
        // passthrough
      case 'active':
      case 'code':
      case 'variation':
      case 'tic':
      case 'color':
      case 'active':
      case 'product_id':
      case 'purchase_quantity':
      case 'minimum_quantity':
      case 'prop65':
      case 'hazmat':
      case 'no_backorder':
      case 'oversized':
      case 'is_kit':
      case 'length':
      case 'width':
      case 'height':
        $this->$name= $value;
        break;
      case 'name':
      case 'short_name':
        $value= preg_replace_callback('/{{(.+?)}}/',
          function ($m) {
            if ($m[1] == 'size') {
              $size= strstr($this->name, ' ', true);
              return
                str_replace('ft"', ' ft.',
                            str_replace('yd"', ' yds.',
                                        str_replace('x', '" × ', $size) . '"'));
            }
            return $this->{$m[1]};
          }, $value);
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
      case 'weight':
        $this->setWeight($value);
        break;
      default:
        throw new \Exception("No way to set '$name' on an item.");
    }
  }

  /* Need to promote a couple of pseudo-fields */
  public function getFields() {
    $fields= parent::getFields();
    $fields[]= 'dimensions';
    $fields[]= 'stock';
    $fields[]= 'sale_price';
    return $fields;
  }

  public function as_array() {
    $data= parent::as_array();
    $data['dimensions']= $this->dimensions();
    $data['stock']= $this->stock();
    $data['on_order']= $this->on_order();
    $data['sale_price']= $this->sale_price();
    return $data;
  }

  public function setDiscount($discount) {
    $discount= preg_replace('/^\\$/', '', $discount);
    if (preg_match('/^(\d*)(\/|%)( off)?$/', $discount, $m)) {
      $discount = $m[1];
      $discount_type = "percentage";
    } elseif (preg_match('/^(\d*\.?\d*)$/', $discount, $m)) {
      $discount = $m[1];
      $discount_type = "fixed";
    } elseif (preg_match('/^\$?(\d*\.?\d*)( off)?$/', $discount, $m)) {
      $discount = $m[1];
      $discount_type = "relative";
    } elseif (preg_match('/^-\$?(\d*\.?\d*)$/', $discount, $m)) {
      $discount = $m[1];
      $discount_type = "relative";
    } elseif (preg_match('/^(def|\.\.\.)$/', $discount)) {
      $discount= null;
      $discount_type= null;
    } else {
      throw new \Exception("Did not understand discount.");
    }
    $this->discount= $discount;
    $this->discount_type= $discount_type;
  }

  public function setStock($stock) {
    $current= $this->stock();
    if ($stock != $current) {
      $cxn= self::factory('Txn')
        ->where_raw("type = 'correction' AND DATE(NOW()) = DATE(created)")
        ->find_one();

      // XXX this is kind of gross, need to think about alternative here
      if (!$cxn) {
        $txn= $GLOBALS['container']->get('\\Scat\\Service\\Txn');
        $cxn= $txn->create('correction');
      }

      $diff= $stock - $current;

      $cost= $this->most_recent_cost() ?: 0.00; // need 0.00 instead of null

      $txn_line= self::factory('TxnLine')
        ->where_equal('txn_id', $cxn->id)
        ->where_equal('item_id', $this->id)
        ->find_one();

      if ($txn_line) {
        $txn_line->ordered+= $diff;
        $txn_line->allocated+= $diff;
        $txn_line->retail_price= $txn_line->ordered < 0 ? $cost : 0.00;
      } else {
        $txn_line= self::factory('TxnLine')->create();
        $txn_line->txn_id= $cxn->id;
        $txn_line->item_id= $this->id;
        $txn_line->ordered= $diff;
        $txn_line->allocated= $diff;
        $txn_line->retail_price= $cost;
      }
      $txn_line->save();
    }
  }

  public function setDimensions($dimensions) {
    list($l, $w, $h)= preg_split('/[^\d.]+/', trim($dimensions));
    $this->length= $l;
    $this->width= $w;
    $this->height= $h;
  }

  public function setWeight($weight) {
    if (preg_match('/([0-9.]+\s+)?([0-9.]+) *oz/', $weight, $m)) {
      $weight= (int)$m[1] + ($m[2] / 16);
    }
    $this->weight= $weight;
  }

  public function dimensions() {
    if ($this->length && $this->width && $this->height)
      return $this->length . 'x' .
             $this->width . 'x' .
             $this->height;
  }

  public function price_overrides() {
    return self::factory('PriceOverride')
            ->where_raw("(pattern_type = 'product' AND pattern = ?) OR
                         (pattern_type = 'like'  AND ? LIKE pattern) OR
                         (pattern_type = 'rlike' AND ? RLIKE pattern)",
                         [ $this->product_id, $this->code, $this->code ])
            ->order_by_asc('minimum_quantity');
  }

  public function override_price() {
    $override= $this->price_overrides()->having('minimum_quantity', 1)->find_one();
    if ($override && $override->discount_type == 'additional_percentage') {
      return $this->calcSalePrice(
        $this->sale_price(),
        'percentage',
        $override->discount
      );
    } elseif ($override) {
      return $this->calcSalePrice(
        $this->retail_price,
        $override->discount_type,
        $override->discount
      );
    }

  }

  public function txns() {
    return self::factory('Txn')
            ->join('txn_line', [ 'txn.id', '=', 'txn_line.txn_id' ])
            ->select('txn.*')
            ->select_expr('AVG(sale_price(retail_price, discount_type, discount))',
                          'sale_price')
            ->select_expr('SUM(allocated)', 'quantity')
            ->group_by('txn.id')
            ->order_by_asc('txn.created')
            ->where('txn_line.item_id', $this->id);

    $q= "SELECT txn.id, txn.created, txn.type, txn.number,
                AVG(sale_price(retail_price, discount_type, discount))
                  AS sale_price,
                SUM(allocated) AS quantity
           FROM txn
           JOIN txn_line ON (txn_line.txn_id = txn.id)
          WHERE txn_line.item_id = {$this->id}
          GROUP BY txn.id
          ORDER BY txn.created";
    return $this->orm->for_table('txn')->raw_query($q);
  }

  public function findVendorItems() {
    $q= "UPDATE vendor_item, item
            SET vendor_item.item_id = item.id
          WHERE (vendor_item.item_id IS NULL OR vendor_item.item_id = 0)
            AND vendor_item.code = item.code
            AND item.id = {$this->id}";
    $this->orm->raw_execute($q);

    $q= "UPDATE vendor_item, barcode
            SET vendor_item.item_id = barcode.item_id
          WHERE (vendor_item.item_id IS NULL OR vendor_item.item_id = 0)
            AND vendor_item.barcode = barcode.code
            AND barcode.item_id = {$this->id}";
    $this->orm->raw_execute($q);
  }

  public function shipping_rate() {
    $rate= $this->hazmat ? 'hazmat-' : '';

    if ($this->oversized) {
      return $rate . "truck";
    }

    if ($this->weight == 0 || $this->length == 0) {
      return $rate . "unknown";
    }

    $size= [$this->height, $this->length, $this->width ];
    sort($size, SORT_NUMERIC);

    if ($size[0] > 8 || $size[1] > 19 || $size[2] > 25) {
      return $rate . "large";
    } else if ($size[0] > 8 || $size[1] > 15 || $size[2] > 18) {
      return $rate . "medium";
    }

    return $rate . "small";
  }

}

class Barcode extends \Scat\Model {
  public function item() {
    return $this->belongs_to('Item')->find_one();
  }
}

class Prop65_Warning extends \Scat\Model {
}

class ItemToImage extends \Scat\Model {
  function delete() {
    $this->orm->use_id_column([ 'item_id', 'image_id' ]);
    return parent::delete();
  }
}
