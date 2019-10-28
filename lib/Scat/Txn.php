<?php
namespace Scat;

class Txn extends \Model implements \JsonSerializable {
  private $_totals;

  public static function create($options) {
    $txn= \Model::factory('Txn')->create();

    foreach ($options as $key => $value) {
      $txn->$key= $value;
    }

    // Generate number based on transaction type
    $q= "SELECT 1 + MAX(number) AS number FROM txn WHERE type = '{$txn->type}'";
    $res= \ORM::for_table('txn')->raw_query($q)->find_one();
    $txn->number= $res->number;

    $txn->save();

    return $txn;
  }

  public function formatted_number() {
    $created= new \DateTime($this->created);
    return $this->type == 'vendor' ?
      ($created->format('Y') > 2013 ?
       $created->format('y') . $this->number : // Y3K
       $created->format('Y') . '-' . $this->number) :
      ($created->format("Y") . "-" . $this->number);
  }

  public function items_source() {
    return $this->has_many('TxnLine', 'txn');
  }

  public function items() {
    return $this->has_many('TxnLine', 'txn')->find_many();
  }

  public function payments() {
    return $this->has_many('Payment', 'txn')->find_many();
  }

  public function person_id() {
    return $this->person;
  }

  public function owner() {
    return $this->belongs_to('Person', 'person')->find_one();
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

    $q= "SELECT ordered, allocated
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
                       WHERE txn.id = payment.txn)
                     AS DECIMAL(9,2)) AS total_paid
           FROM txn
           LEFT JOIN txn_line ON (txn.id = txn_line.txn)
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

  public function jsonSerialize() {
    return $this->as_array();
  }
}
