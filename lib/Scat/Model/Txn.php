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

  function clearItems() {
    $this->orm->get_db()->beginTransaction();
    $this->items()->delete_many();
    $this->filled= null;
    $this->save();
    $this->orm->get_db()->commit();
    return true;
  }

  private function _loadTotals() {
    if ($this->_totals) return $this->_totals;

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
    return ($this->_totals= $st->fetch(\PDO::FETCH_ASSOC));
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
    return $total['ordered'];
  }

  public function getInvoicePDF($variation= '') {
    $loader= new \Twig\Loader\FilesystemLoader('../ui/');
    $twig= new \Twig\Environment($loader, [ 'cache' => false ]);

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

  public function jsonSerialize() {
    return $this->as_array();
  }
}
