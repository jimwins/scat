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

  public function notes() {
    return $this->has_many('Note', 'attach_id');
  }

  public function payments() {
    return $this->has_many('Payment');
  }

  public function person() {
    return $this->belongs_to('Person')->find_one();
  }

  public function shipping_address() {
    return $this->belongs_to('Address', 'shipping_address_id')->find_one();
  }

  public function dropships() {
    return $this->has_many('Txn', 'returned_from_id')
                ->where('type', 'vendor');
  }

  public function returned_from() {
    return $this->belongs_to('Txn', 'returned_from_id')->find_one();
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
                  SUM(IF(txn_line.taxfree, 1, 0) *
                    IF(type = 'customer', -1, 1) * ordered *
                    sale_price(retail_price, discount_type, discount)),
                  2) AS DECIMAL(9,2))
                untaxed,
                CAST(ROUND_TO_EVEN(
                  SUM(IF(txn_line.taxfree, 0, 1) *
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

    return ($this->_totals= $st->fetch(\PDO::FETCH_ASSOC));
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
    $loader= new \Twig\Loader\FilesystemLoader('../ui/');
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

  public function shipments() {
    return $this->has_many('Shipment');
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

      $breaks= explode(',', $d->breaks);
      $discount_types= explode(',', $d->discount_types);
      $discounts= explode(',', $d->discounts);

      foreach ($breaks as $i => $qty) {
        if ($count >= $qty) {
          $new_discount_type= $discount_types[$i];
          $new_discount= $discounts[$i];
        }
      }

      foreach ($items->find_many() as $item) {
        if ($new_discount) {
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

    foreach ($this->payments() as $payment) {
      if ($payment->method == 'discount' && $payment->discount) {
        // Force total() to be calculated
        unset($txn->_totals);
        $payment->amount= $payment->discount / 100 * $txn->total();
        $payment->save();
      }
    }
  }

  public function recalculateTax(\Scat\Service\Tax $tax) {
    if ($this->type != 'customer') {
      return;
    }

    if ($this->shipping_address_id > 1) {
      error_log("Don't know how to calculate tax on shipped orders.");
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

    foreach ($this->items()->find_many() as $line) {
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

  public function loyalty() {
    return $this->has_many('Loyalty');
  }

  public function rewardLoyalty() {
    // No person? No loyalty.
    if (!$this->person_id)
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
                'Used online' note,
                -1 * JSON_EXTRACT(CONVERT(data USING utf8mb4), '$.points')
                  points
           FROM payment
          WHERE txn_id = {$this->id}
            AND method = 'loyalty'";

    $this->orm->raw_execute($q);

    // No rewards for this txn?
    if ($this->no_rewards)
      return;

    // Award new points
    $taxed= $this->taxed();
    $points= (int)$taxed *
              (defined('LOYALTY_MULTIPLIER') ? LOYALTY_MULTIPLIER : 1);
    if ($points == 0 && $taxed > 0) $points= 1;

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

  public function as_array() {
    $res= parent::as_array();
    $res['items']= $this->items()->find_many();
    return $res;
  }
}
