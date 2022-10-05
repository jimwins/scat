<?
namespace Scat\Model;

class Item extends \Scat\Model {
  private $_cache= [];

  /* XXX Legacy, should get from parent product */
  public function brand() {
    return $this->belongs_to('Brand', 'brand')->find_one();
  }

  private static function expand_pad_details($m) {
    $ret= [];
    // m[1] = sheets
    $ret[]= "{$m[1]} sheets";
    // m[2] = binding
    switch ($m[2]) {
      case 'PB':
        $ret[]= "Perfect Bound"; break;
      case 'TB':
        $ret[]= "Tape Bound"; break;
      case 'WB':
        $ret[]= "Wire Bound"; break;
      case 'HB':
        $ret[]= "Hard Bound"; break;
      case 'SB':
        $ret[]= "Staple Bound"; break;
      case 'SMB':
        $ret[]= "Sewn Binding"; break;
      case 'SH':
        $ret[]= "Unbound"; break;
      default:
        error_log("Can't explain binding {$m[2]}");
    }
    // m[3] = weight
    // m[4] = weight units (# or gsm)
    if (isset($m[3])) {
      $ret[]= $m[3] . " " . ($m[4] == '#' ? ' lbs.' : 'gsm');
    }
    // m[5] = CP, HP, R
    if (isset($m[5])) {
      switch ($m[5]) {
        case 'CP':
          $ret[]= "Cold Press"; break;
        case 'HP':
          $ret[]= "Hot Press"; break;
        case 'R':
          $ret[]= "Rough"; break;
        default:
          error_log("Can't explain paper finish {$m[5]}");
      }
    }

    return "(" . join(", ", $ret) . ")";
  }

  /* Turn name into a more user-friendly title (for feeds, website) */
  public function title() {
    $title= $this->name;
    // 3in Ruler -> 3" Ruler
    $title= preg_replace('/^([-\\/\d.]+)in /', '\1" ', $title);
    // 9x12 Canvas -> 9" x 12" Canvas
    $title= preg_replace('/^([-\\/\d.]+)x([-\\/\d.]+) /', '\1" x \2" ', $title);
    // 9x12ft Whatever -> 9" x 12' Whatever
    $title= preg_replace('/^([-\\/\d.]+)x([-\\/\d.]+)ft /', '\1" x \2\' ', $title);
    // 9x12yd Whatever -> 9" x 12 yd. Whatever
    $title= preg_replace('/^([-\\/\d.]+)x([-\\/\d.]+)yd /', '\1" x \2 yd. ', $title);
    // 9x12 Sketch 80/WB/90# -> 9" x 12" Sketch (80 sheets, Wirebound, 90 lbs.)
    // note: has to be at the end of the name.
    $title= preg_replace_callback(
      '!(\d+)/(\w\w\w?)(?:/([\d.]+)(gsm|#)(\w\w?)?)?$!',
      "self::expand_pad_details",
      $title);
    // 5oz Titanium White Acrylic
    $title= preg_replace('/^([\d.]+)oz /', '\1 oz. ', $title);

    return $title;
  }

  public function product() {
    return @$this->_cache['product'] ?:
      ($this->_cache['product']= $this->belongs_to('Product')->find_one());
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
    return $this->generate_upc(sprintf("000000%05d", $this->id));
  }

  public function in_kits() {
    return $this->has_many_through('Item', 'KitItem', null, 'kit_id', null, 'id')
      ->where('active', 1)
      ->find_many();
  }

  public function vendor_items($active= 1) {
    return $this->has_many('VendorItem')
                ->where_gte('vendor_item.active', $active);
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

  public function siblings() {
    return $this->factory('Item')
                ->where('product_id', $this->product_id)
                ->where_not_equal('id', $this->item_id)
                ->where('active', 1)
                ->find_many();
  }

  public function default_image() {
    $media= $this->media();
    if (!$media) {
      if ($this->siblings() > 1) {
        return null;
      }
      $media= $product->media();
    }

    if (!$media || !$media[0]) return null;

    return $media[0]->large_square();
  }

  public function brand_name() {
    $product= $this->product();
    if ($product) {
      $brand= $product->brand();
      if ($brand) {
        return $brand->name;
      }
    }
    return "";
  }

  public function category() {
    $product= $this->product();
    if ($product) {
      $subdept= $product->dept();
      if ($subdept) {
        $dept= $subdept->parent();
      }
    }
    if (!$product || !$subdept || !$dept)
      return "";

    return
      $dept->name . ' > ' .
      $subdept->name . ' > ' .
      $product->name;
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

  public function url_params() {
    $params= $this->product_id ? $this->product()->url_params() : [];
    return array_merge($params, [ 'item' => $this->code ]);
  }

  public function sale_price() {
    return $this->calcSalePrice(
      $this->retail_price,
      $this->discount_type,
      $this->discount
    );
  }

  public function stock($when= null) {
    if ($this->is_kit) {
      $items= $this->has_many('KitItem', 'kit_id')->find_many();
      if (!$items) return 0;
      $qtys= array_map(function ($item) use ($when) {
        return $item->item()->stock($when);
      }, $items);
      return min($qtys);
    } else {
      // Web uses a view that has stock set already, so we use that here
      if (isset($this->stock)) return $this->stock;
      if ($when) {
        return $this->has_many('TxnLine')
                    ->join('txn', [ 'txn_line.txn_id', '=', 'txn.id' ])
                    ->where_lt('txn.created', $when)
                    ->sum('allocated') ?: 0;
      } else {
        return $this->has_many('TxnLine')
                    ->sum('allocated') ?: 0;
      }
    }
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
      case 'retail_price':
        $value= preg_replace('/^\\$/', '', $value);
        // passthrough
      case 'active':
      case 'code':
      case 'description':
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
      case 'packaged_for_shipping':
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
            ->where_raw("((pattern_type = 'product' AND pattern = ?) OR
                         (pattern_type = 'like'  AND ? LIKE pattern) OR
                         (pattern_type = 'rlike' AND ? RLIKE pattern))",
                         [ $this->product_id, $this->code, $this->code ])
            ->order_by_asc('minimum_quantity');
  }

  public function override_price() {
    $stock= $this->stock();
    $override= $this->price_overrides()->where_raw("(? >= `in_stock`)", [ $stock ])->having('minimum_quantity', 1)->find_one();
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

  function can_ship_first_class_package() {
    if ($this->hazmat || $this->oversized) {
      return false;
    }

    $boxes= [
      [  5,     5,     3.5,  0.13, 0.39 ],
      [  9,     5,     3,    0.21, 0.53 ],
      [  9,     8,     8,    0.48, 0.86 ],
      [ 12.25,  3,     3,    0.19, 1.03 ],
      [ 10,     7,     5,    0.32, 0.82 ],
      [ 12,     9.5,   4,    0.44, 1.01 ],
      [ 12,     9,     9,    0.65, 0.98 ],
    ];

    // Don't know?
    if (!$this->weight || !$this->width || !$this->length || !$this->height) {
      return null;
    }

    $box= \Scat\Service\Shipping::fits_in_box($boxes, [
            [ $this->width, $this->height, $this->length ]
          ]);

    if ($box && $this->weight + $box[3] < 1)
    {
      return true;
    }

    return false;
  }

  function can_ship_free() {
    $boxes= [ [ 33, 19, 4 ], [ 20, 13, 10 ], [ 54, 4, 4 ] ];

    // Don't know?
    if (!$this->weight || !$this->width || !$this->length || !$this->height) {
      return null;
    }

    if ($this->oversized) {
      return false; // Easy no.
    }

    return ($this->weight < 10 &&
            \Scat\Service\Shipping::fits_in_box($boxes, [
              [ $this->width, $this->height, $this->length ]
            ]));
  }

  public function shipping_rate() {
    if ($this->oversized) {
      return "truck";
    }

    if ($this->weight == 0 || $this->length == 0 || $this->width == 0 || $this->height == 0) {
      return "unknown";
    }

    if ($this->can_ship_first_class_package()) {
      return "firstclass";
    }

    if ($this->can_ship_free()) {
      return "possiblyfree";
    }

    return "standard";
  }

  public function estimate_local_delivery_rate() {
    $dims= [ [ $this->width, $this->height, $this->length ] ];
    $local= \Scat\Service\Shipping::get_base_local_delivery_rate($dims, $this->weight);
    if ($local) {
      // assume 2-mile minimum delivery
      return ($local + 3) * 1.05;
    }
    return null;
  }

  public function estimate_shipping_rate() {
    if ($this->can_ship_first_class_package()) {
      return 4.99;
    }

    $box= \Scat\Service\Shipping::get_shipping_box([ [ $this->width, $this->height, $this->length ] ]);
    if ($box) {
      return 9 + $box[4];
    }
  }

  public function variations() {
    return $this->factory('Item')
      ->where('product_id', $this->product_id)
      ->where('short_name', $this->short_name)
      ->where_not_equal('variation', $this->variation)
      ->where('active', 1)
      ->order_by_asc('code')
      ->find_many();
  }

  public function has_vendor_with_salsify() {
    return $this->vendor_items()
                ->join('person', [ 'vendor_id', '=', 'person.id' ])
                ->where_raw('LENGTH(salsify_url)')
                ->count();
  }

  /* XXX remove when we add description to item */
  public function description() {
    return null;
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
