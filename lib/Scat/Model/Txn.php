<?php
namespace Scat\Model;

class Txn extends \Scat\Model {
  private $_totals;

  public function formatted_number() {
    $created= new \DateTime($this->created);
    return $this->type == 'vendor' ?
      ($created->format('Y') > 2013 ?
       $created->format('y') . $this->number : // Y3K
       $created->format('Y') . '-' . $this->number) :
      ($created->format("Y") . "-" . $this->number);
  }

  public function friendly_type() {
    switch ($this->type) {
      case 'vendor':
        return 'Purchase Order';
      case 'correction':
        return 'Correction';
      case 'drawer':
        return 'Till Count';
      case 'customer':
        return $this->returned_from_id ? 'Return' : 'Sale';
    }
  }

  public function items() {
    return $this->has_many('TxnLine');
  }

  public function allow_item_changes() {
    if ($this->type == 'vendor') {
      $allowed= [
        'new', 'processing', 'waitingforitems', 'shipping', 'shipped'
      ];
    } else {
      $allowed= [ 'new', 'filled', 'template' ];
    }

    return in_array($this->status, $allowed);
  }

  public function cost_of_goods() {
    $cogs= 0;
    foreach ($this->items()->find_many() as $line) {
      $cogs+= $line->cost_of_goods();
    }
    return -$cogs;
  }

  public function stocked() {
    $stock= 1;
    foreach ($this->items()->find_many() as $line) {
      if ($line->tic != '00000') continue;
      $item_stock= $line->item()->stock($this->created);
      if ($item_stock < -($line->ordered)) {
        $stock= 0;
      }
    }
    return $stock;
  }

  public function notes($only_todo= false) {
    $notes=
      $this->has_many('Note', 'attach_id')
        ->where('parent_id', 0)
        ->where('kind', 'txn');
    if ($only_todo) {
      $notes= $notes->where('todo', 1);
    }
    return $notes;
  }

  public function createNote() {
    $note= $this->factory('Note')->create();
    $note->kind= 'txn';
    $note->attach_id= $this->id;

    return $note;
  }

  public function payments() {
    return $this->has_many('Payment');
  }

  public function cost_of_processing() {
    $cost= 0;
    // TODO this should be in the Payment model
    foreach ($this->payments()->find_many() as $line) {
      switch ($line->method) {
      case 'credit':
        $cost+= $line->amount * 0.02;
        break;
      case 'amazon':
      case 'stripe':
        $cost+= ($line->amount * 0.029) + 0.30;
        break;
      case 'paypal':
        $data= json_decode($line->data);
        if ($data->purchase_units) {
          foreach ($data->purchase_units as $unit) {
            foreach ($unit->payments->captures as $capture) {
              $cost+= $capture->seller_receivable_breakdown->paypal_fee->value;
            }
          }
        }
        break;
      case 'bad':
        $cost+= $line->amount;
      default:
        /* nothing */
      }
    }
    return $cost;
  }


  public function canPay($method, $amount) {
    // only 'gift' and 'cash' allow giving change
    $change= (($method == 'cash' || $method == 'gift') ? true : false);

    if ($method == 'discount') {
      // assume it's good
      return true;
    }

    if (!$change &&
        (($this->total() >= 0 &&
          bccomp(bcadd($amount, $this->total_paid()), $this->total()) > 0)
         ||
         ($this->total() < 0 &&
          bccomp(bcadd($amount, $this->total_paid()), $this->total()) < 0)))
    {
      return false;
    }

    return true;
  }

  public function used_cash() {
    return $this->payments()->where_in('method', [ 'cash', 'change' ])->count();
  }

  public function used_loyalty_reward() {
    return $this->payments()->where('method', 'loyalty')->count();
  }

  public function used_discount() {
    return $this->payments()->where('method', 'discount')->count();
  }

  public function change() {
    return $this->payments()->where_in('method', [ 'change' ])->sum('amount');
  }

  public function person() {
    return $this->belongs_to('Person')->find_one();
  }

  public function shipping_address() {
    return $this->belongs_to('Address', 'shipping_address_id')->find_one();
  }

  public function is_bike_delivery() {
    /* XXX Fix hardcoded list */
    return $this->shipping_address_id > 1 &&
      $this->items()->where_in('item_id', [ 11405, 87072, 89017 ])->find_one();
  }

  public function is_local_delivery() {
    /* XXX Fix hardcoded list */
    return $this->shipping_address_id > 1 &&
           $this->items()->join('item', [ 'item.id', '=', 'txn_line.item_id' ])->where('item.code', 'ZZ-DELIVERY-VEHICLE')->count();
  }

  public function delivery_details() {
    return $this->items()->select('txn_line.*')->join('item', [ 'item.id', '=', 'txn_line.item_id' ])->where('item.code', 'ZZ-DELIVERY-VEHICLE')->find_one();
  }

  public function has_hazmat_items() {
    return $this->items()->join('item', [ 'item.id', '=', 'txn_line.item_id' ])->where_gt('item.hazmat', 0)->count();
  }

  public function shipping_method() {
    if ($this->shipping_address_id <= 1)
      return null;

    $item= $this->items()->where('tic', '11000')->find_one();

    if ($item) {
      return $item->data()->method ?: 'default';
    }

    return 'default';
  }

  public function dropships() {
    return $this->has_many('Txn', 'returned_from_id')
                ->where('type', 'vendor');
  }

  public function returned_from() {
    return $this->belongs_to('Txn', 'returned_from_id')->find_one();
  }

  public function returns() {
    return $this->has_many('Txn', 'returned_from_id')
                ->where('type', $this->type);
  }

  function clearItems() {
    $this->orm->get_db()->beginTransaction();
    $this->items()->delete_many();
    $this->filled= null;
    if ($this->status == 'filled') $this->status= 'new';
    $this->save();
    $this->orm->get_db()->commit();
    return true;
  }

  private function _loadTotals() {
    if ($this->_totals) return $this->_totals;

    /* turn off logging here, it's just too much */
    $this->orm->configure('logging', false);

    $q= "SELECT ordered, allocated,
                taxed, untaxed,
                CAST(tax_rate AS DECIMAL(9,2)) tax_rate,
                taxed + untaxed subtotal,
                IF(uuid IS NOT NULL, /* Tax calculated per-line */
                   tax,
                   CAST(ROUND_TO_EVEN(taxed * (tax_rate / 100), 2)
                        AS DECIMAL(9,2))) AS tax,
                IF(uuid IS NOT NULL,
                   taxed + untaxed + tax,
                   CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                        AS DECIMAL(9,2))) total,
                IFNULL(total_paid, 0.00) total_paid
          FROM (SELECT
                txn.uuid,
                SUM(ordered) * IF(txn.type = 'customer', -1, 1) AS ordered,
                SUM(allocated) * IF(txn.type = 'customer', -1, 1) AS allocated,
                CAST(ROUND_TO_EVEN(
                  SUM(IF(IF(uuid IS NOT NULL,
                            txn_line.tax > 0,
                            !txn_line.taxfree),
                          0,
                          1) *
                      IF(type = 'customer', -1, 1) * ordered *
                      sale_price(retail_price, discount_type, discount)),
                  2) AS DECIMAL(9,2))
                untaxed,
                CAST(ROUND_TO_EVEN(
                  SUM(IF(IF(uuid IS NOT NULL,
                            txn_line.tax > 0,
                            !txn_line.taxfree),
                          1,
                          0) *
                      IF(type = 'customer', -1, 1) * ordered *
                      sale_price(retail_price, discount_type, discount)),
                  2) AS DECIMAL(9,2))
                taxed,
                tax_rate,
                SUM(tax) AS tax,
                CAST((SELECT SUM(amount)
                        FROM payment
                       WHERE txn.id = payment.txn_id)
                     AS DECIMAL(9,2)) AS total_paid
           FROM txn
           LEFT JOIN txn_line ON (txn.id = txn_line.txn_id)
          WHERE txn.id = {$this->id}) t";
    $this->orm->raw_execute($q);
    $st= $this->orm->get_last_statement();

    $this->orm->configure('logging', true);

    $this->_totals= $st->fetch(\PDO::FETCH_ASSOC);

    return $this->_totals;
  }

  public function taxed() {
    return $this->_loadTotals()['taxed'];
  }

  public function subtotal() {
    return $this->_loadTotals()['subtotal'];
  }

  public function tax() {
    return $this->_loadTotals()['tax'];
  }

  public function total() {
    return $this->_loadTotals()['total'];
  }

  public function total_paid() {
    return $this->_loadTotals()['total_paid'];
  }

  public function due() {
    $total= $this->_loadTotals();
    return $total['total'] - $total['total_paid'];
  }

  public function ordered() {
    $total= $this->_loadTotals();
    return $total['ordered'];
  }

  public function allocated() {
    $total= $this->_loadTotals();
    return $total['allocated'];
  }

  public function getInvoicePDF($variation= '') {
    $loader= new \Twig\Loader\FilesystemLoader([ '../ui/pos/','../ui/shared' ]);
    $twig= new \Twig\Environment($loader, [ 'cache' => false ]);
    $twig->addExtension(new \Scat\TwigExtension());

    $template= $twig->load('print/invoice.html');
    $html= $template->render([ 'txn' => $this, 'variation' => $variation ]);

    define('_MPDF_TTFONTDATAPATH', '/tmp/ttfontdata');
    @mkdir(_MPDF_TTFONTDATAPATH);

    $mpdf= new \Mpdf\Mpdf([ 'mode' => 'utf-8', 'format' => 'letter',
                            'tempDir' => '/tmp',
                            'default_font_size' => 11  ]);
    $mpdf->setAutoTopMargin= 'stretch';
    $mpdf->setAutoBottomMargin= 'stretch';
    $mpdf->writeHTML($html);

    return $mpdf;
  }

  public function getReceiptPDF($variation= '') {
    $loader= new \Twig\Loader\FilesystemLoader([ '../ui/pos/','../ui/shared' ]);
    $twig= new \Twig\Environment($loader, [ 'cache' => false ]);
    $twig->addExtension(new \Scat\TwigExtension());

    $template= $twig->load('print/receipt.html');
    $html= $template->render([ 'txn' => $this, 'variation' => $variation ]);

    define('_MPDF_TTFONTDATAPATH', '/tmp/ttfontdata');
    @mkdir(_MPDF_TTFONTDATAPATH);

    $mpdf= new \Mpdf\Mpdf([ 'mode' => 'utf-8', 'format' => 'letter',
                            'tempDir' => '/tmp',
                            'margin_left' => 15, 'margin_right' => 15,
                            'margin_top' => 9, 'margin_bottom' => 10,
                            'default_font_size' => 28  ]);
    $mpdf->setAutoTopMargin= 'stretch';
    $mpdf->setAutoBottomMargin= 'stretch';
    $mpdf->writeHTML($html);

    return $mpdf;
  }

  public function getInvoiceTsv() {
    $lines[]= join("\t", [
      'SKU',
      'Code',
      'Name',
      'UPC',
      'Net',
      'Quantity',
      'Ext',
    ]);

    foreach ($this->items()->find_many() as $line) {
      $lines[]= join("\t", [
        $line->vendor_sku(),
        $line->code(),
        $line->name(),
        $line->vendor_item()->barcode,
        $line->retail_price,
        $line->ordered,
        (new \Decimal\Decimal($line->retail_price)) * $line->ordered,
      ]);
    }

    return join("\r\n", $lines);
  }

  public function shipping() {
    return $this->items()->where('tic', '11000')->sum('retail_price');
  }

  public function shipments() {
    return $this->has_many('Shipment');
  }

  public function cost_of_shipping() {
    $cost= 0;
    foreach ($this->shipments()->find_many() as $line) {
      if ($line->status != 'cancelled') {
        $cost+= $line->rate;
      }
    }
    return $cost;
  }

  public function applyPriceOverrides(\Scat\Service\Catalog $catalog) {
    // Not an error, but we don't do anything
    if ($this->type != 'customer') {
      return;
    }

    $discounts= $catalog->getPriceOverrides();

    foreach ($discounts as $d) {
      if ($d->pattern_type == 'product') {
        $condition= "`product_id` = '{$d->pattern}'";
      } else {
        $condition= "`code` {$d->pattern_type} '{$d->pattern}'";
      }

      $items= $this->items()
        ->select('txn_line.*')
        ->select('item.retail_price', 'original_retail_price')
        ->select('item.discount', 'original_discount')
        ->select('item.discount_type', 'original_discount_type')
        ->join('item', [ 'item_id', '=', 'item.id' ])
        ->where('txn_id', $this->id)
        ->where_null('kit_id')
        ->where_raw($condition)
        ->where_raw('NOT `discount_manual`');

      /* turn off logging here, it's just too much */
      $this->orm->configure('logging', false);
      $count= abs($items->sum('ordered'));
      $items->limit(null); // reset limit that sum() injects into $items
      $this->orm->configure('logging', true);

      if (!$count) {
        continue;
      }

      $new_discount= 0;
      $new_discount_type= '';
      $new_in_stock= 0;

      $breaks= explode(',', $d->breaks);
      $discount_types= explode(',', $d->discount_types);
      $discounts= explode(',', $d->discounts);
      $in_stocks= explode(',', $d->in_stocks);

      foreach ($breaks as $i => $qty) {
        if ($count >= $qty) {
          $new_discount_type= $discount_types[$i];
          $new_discount= $discounts[$i];
          $new_in_stock= $in_stocks[$i];
        }
      }

      foreach ($items->find_many() as $item) {
        if ($new_discount) {
          /* Verify we meet the in_stock criteria */
          if ($new_in_stock && $item->item()->stock() == 0) {
            error_log("skipping discount, not in stock!\n");
            continue;
          }
          if ($new_discount_type != 'additional_percentage') {
            $item->discount= $new_discount;
            $item->discount_type= $new_discount_type;
          } else {
            $item->discount= $this->calcSalePrice(
              $this->calcSalePrice($item->original_retail_price,
                                   $item->original_discount_type,
                                   $item->original_discount),
              'percentage',
              $new_discount
            );
            $item->discount_type= 'fixed';
          }
        } else {
          $item->discount= $item->original_discount;
          $item->discount_type= $item->original_discount_type;
        }
        $item->save();
      }
    }

    foreach ($this->payments()->find_many() as $payment) {
      if ($payment->method == 'discount' && $payment->discount) {
        $payment->amount= $payment->discount / 100 * $this->total();
        $payment->save();
        // Force total() to be calculated
        $this->flushTotals();
      }
    }
  }

  public function flushTotals() {
    $this->_totals= null;
  }

  public function recalculateTaxOnReturns() {
    $original= $this->returned_from();

    foreach ($this->items()->where_not_null('returned_from_id')->find_many()
              as $i)
    {
      $orig_item= $original->items()->find_one($i->returned_from_id);

      $tax= (new \Decimal\Decimal($orig_item->tax) / $orig_item->ordered);
      $tax= $i->ordered * $tax;
      $tax= (string)$tax->round(2, \Decimal\Decimal::ROUND_HALF_UP);

      if ($tax != $i->tax) {
        $i->tax= $tax;
        $i->save();
      }
    }
  }

  public function generateCartItems() {
    $cartItems= []; $index_map= [];
    $n= 1;

    foreach ($this->items()->where_null('returned_from_id')->where_null('kit_id')->find_many()
              as $i)
    {
      $tic= $i->tic;
      $index= ($tic == '11000') ? 0 : $n++;
      $index_map[$index]= $i->id;
      $cartItems[]= [
        'Index' => $index,
        'ItemID' => ($tic == '11000') ? 'shipping' : $i->item_id,
        'TIC' => $tic,
        'Price' => $i->sale_price(),
        'Qty' => -1 * $i->ordered,
      ];
    }

    return [ $cartItems, $index_map ];
  }

  public function recalculateTax(\Scat\Service\Tax $tax) {
    if ($this->type != 'customer') {
      return;
    }

    if ($this->returned_from_id) {
      $this->recalculateTaxOnReturns();
    }

    if ($this->shipping_address_id > 1) {
      $address= $this->shipping_address();

      $zip= explode('-', $address->zip);

      // Look up all non-returned items
      $data= [
        'customerId' => $this->person_id ?: 0,
        'cartId' => $this->uuid,
        'deliveredBySeller' => false,
        // XXX get from default shipping address
        'origin' => [
          'Zip4' => '1320',
          'Zip5' => '90014',
          'State' => 'CA',
          'City' => 'Los Angeles',
          'Address2' => '',
          'Address1' => '645 S Los Angeles St',
        ],
        'destination' => [
          'Zip4' => $zip[1] ?? '',
          'Zip5' => $zip[0],
          'State' => $address->state,
          'City' => $address->city,
          'Address2' => $address->address2,
          'Address1' => $address->address1,
        ],
        'cartItems' => [],
      ];

      list($cartItems, $index_map)= $this->generateCartItems();

      $data['cartItems']= $cartItems;

      // Done if there's nothing to look up
      if (!count($data['cartItems'])) return;

      $response= $tax->lookup($data);

      if ($response->ResponseType < 2) {
        throw new \Exception($response->Messages[0]->Message);
      }

      foreach ($response->CartItemsResponse as $i) {
        $line= $this->items()->find_one($index_map[$i->CartItemIndex]);
        $line->tax= $i->TaxAmount;
        $line->save();
      }

      return;
    }

    /*
     * According to TaxCloud technical support in January 2017:
     *   We round up at 5, to the fifth decimal, except for Florida and
     *   Maryland, which rounds up at 9, not 5.
     *
     * And this matches the Streamlined Sales Tax agreement (section 324).
     *
     * There's no handling for FL and MD here.
     */
    $tax_rate= new \Decimal\Decimal($this->tax_rate) / 100;

    foreach ($this->items()->where_null('returned_from_id')->find_many() as $line) {
      if (!in_array($line->tic, [ '91082', '10005', '11000' ])) {
        $tax= ($line->ordered * -1) *
              new \Decimal\Decimal($line->sale_price()) *
              $tax_rate;
        $tax= (string)$tax->round(2, \Decimal\Decimal::ROUND_HALF_UP);
        if ($tax != $line->tax) {
          $line->tax= $tax;
          $line->save();
        }
      }
    }
  }

  public function captureTax(\Scat\Service\Tax $tax, $force= false) {
    if ($this->tax_captured) {
      throw new \Exception("Tax already captured.");
    }

    /* If tax on an exchange was a wash, we don't bother reporting it */
    if ($this->tax() != 0) {
      // Is this a return or exchange?
      if ($this->returned_from_id) {
        $returned_from= $this->returned_from();

        $data= [
          'orderID' => $returned_from->uuid,
          'returnedDate' => $this->paid,
          'cartItems' => [],
        ];

        // These are the cartItems and index_map of the *returned txn*
        list($cartItems, $index_map)=
          $returned_from->generateCartItems();
        $index_map= array_flip($index_map); // we're going from id to index

        $tax_returned= 0.00;

        foreach ($this->items()->where_not_null('returned_from_id')->where_null('kit_id')->find_many()
                  as $i)
        {
          $index= $index_map[$i->returned_from_id];

          $item= null;
          foreach ($cartItems as $v) {
            if ($v['Index'] == $index) {
              $item= $v;
              break;
            }
          }

          if (!$item) {
            throw new \Exception("Unable to find {$i->returned_from_id} in original transaction");
          }

          if ($i->tic == '11000') {
            $item['ItemID']= 'shipping';
          }
          $item['Qty']= $i->ordered;

          $data['cartItems'][]= $item;
          $tax_returned+= $i->tax;
        }

        if (count($data['cartItems']) && $tax_returned != 0) {
          $response= $tax->returned($data);

          if ($response->ResponseType < 2) {
            throw new \Exception($response->Messages[0]->Message);
          }
        } else {
          error_log("No items to be returned for transaction {$this->id}\n");
        }
      }

      // If we have new items, have to report it as new sale
      if ($this->items()->where_null('returned_from_id')->count()) {
        // Was this a local transaction or return? If so, lookup the tax
        if (!$this->online_sale_id || $this->returned_from_id || $force) {
          // XXX get from default shipping address
          $default_address= [
            'Zip4' => '1320',
            'Zip5' => '90014',
            'State' => 'CA',
            'City' => 'Los Angeles',
            'Address2' => '',
            'Address1' => '645 S Los Angeles St',
          ];
          if ($this->shipping_address_id) {
            $address= $this->shipping_address();
            list($zip5, $zip4)= explode('-', $address->zip);
            $destination= [
              'Zip4' => $zip4,
              'Zip5' => $zip5,
              'State' => $address->state,
              'City' => $address->city,
              'Address2' => $address->street2 ?? '',
              'Address1' => $address->street1,
            ];
          } else {
            $destination= $default_address;
          }
          // Look up all non-returned items
          $data= [
            'customerId' => $this->person_id ?: 0,
            'cartId' => $this->uuid,
            'deliveredBySeller' => false,
            'origin' => $default_address,
            'destination' => $destination,
            'cartItems' => [],
          ];

          $index_map= []; $n= 1;

          foreach ($this->items()->where_null('returned_from_id')->where_null('kit_id')->find_many()
                    as $i)
          {
            $tic= $i->tic;
            $index= ($tic == '11000') ? 0 : $n++;
            $index_map[$index]= $i->id;
            $data['cartItems'][]= [
              'Index' => $index,
              'ItemID' => ($tic == '11000') ? 'shipping' : $i->item_id,
              'TIC' => $tic,
              'Price' => $i->sale_price(),
              'Qty' => -1 * $i->ordered,
            ];
          }

          if (count($data['cartItems'])) {
            $response= $tax->lookup($data);

            if ($response->ResponseType < 2) {
              throw new \Exception($response->Messages[0]->Message);
            }
          }
        }

        $data= [
          'customerID' => $this->person_id ?: 0,
          'cartID' => $this->uuid,
          'orderID' => $this->uuid,
          'dateAuthorized' => $this->paid,
          'dateCaptured' => $this->paid,
        ];

        $response= $tax->authorizedWithCapture($data);
        if ($response->ResponseType < 2) {
          throw new \Exception($response->Messages[0]->Message);
        }
      }
    }

    $this->set_expr('tax_captured', 'NOW()');
    $this->save();
  }

  public function loyalty() {
    return $this->has_many('Loyalty');
  }

  public function points_earned() {
    if ($this->no_rewards)
      return 0;

    $points= (int)$this->subtotal();

    // subtract any gift cards and shipping charges
    // relies on quantity being 1 of these
    $points-= (int)$this->items()->where_in('tic', [ '10005', '11000' ])->sum('retail_price');

    // subtract amount paid with loyalty points
    $points-= $this->payments()->where('method', 'loyalty')->sum('amount');

    // always get at least one point on positive transaction
    if ($points <= 0 && $this->subtotal() > 0) $points= 1;

    $points*= (defined('LOYALTY_MULTIPLIER') ? LOYALTY_MULTIPLIER : 1);

    return $points;
  }

  public function rewardLoyalty() {
    // No person? No loyalty.
    if (!$this->person_id)
      return;

    // Not paid yet? No loyalty yet.
    if (!$this->paid)
      return;

    // Use rewards (added as items, old style)
    $q= "INSERT INTO loyalty (txn_id, person_id, processed, note, points)
         SELECT {$this->id} txn_id,
                {$this->person_id} person_id,
                NOW() processed,
                name note,
                cost * allocated points
           FROM loyalty_reward
           JOIN txn_line ON loyalty_reward.item_id = txn_line.item_id
           JOIN item ON txn_line.item_id = item.id
          WHERE txn_line.txn_id = {$this->id}";

    $this->orm->raw_execute($q);

    // Use rewards (used as payments, new style)
    $q= "INSERT INTO loyalty (txn_id, person_id, processed, note, points)
         SELECT {$this->id} txn_id,
                {$this->person_id} person_id,
                NOW() processed,
                'Used for purchase' note,
                -1 * JSON_EXTRACT(CONVERT(data USING utf8mb4), '$.cost')
                  points
           FROM payment
          WHERE txn_id = {$this->id}
            AND method = 'loyalty'";

    $this->orm->raw_execute($q);

    /* Mark them captured */
    foreach ($this->payments()->where('method', 'loyalty')->find_many()
              as $payment)
    {
      $payment->set_expr('captured', 'NOW()');
      $payment->save();
    }

    // No rewards for this txn?
    if ($this->no_rewards)
      return;

    // Award new points
    $points= $this->points_earned();

    $loyalty= $this->loyalty()->create();
    $loyalty->txn_id= $this->id;
    $loyalty->person_id= $this->person_id;
    $loyalty->set_expr('processed', 'NOW()');
    $loyalty->note= 'Pt Earned';
    $loyalty->points= $points;
    $loyalty->save();
  }

  public function clearLoyalty() {
    $this->loyalty()->delete_many();
  }

  public function captureAmazonPayments(\Scat\Service\AmazonPay $amzn) {
    foreach ($this->payments()->where('method', 'amazon')->where_null('captured')->find_many() as $payment) {
      $capture= $payment->amznCapture($amzn);
      if ($capture) {
        error_log("Captured Amazon Pay payment: " . json_encode($capture));
        $payment->set_expr('captured', 'NOW()');
        $payment->save();
      }
    }
  }

  public function as_array() {
    $res= parent::as_array();
    $res['items']= $this->items()->find_many();
    $res['shipments']= $this->shipments()->find_many();
    return $res;
  }
}
