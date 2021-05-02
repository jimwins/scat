<?php
namespace Scat\Service;

class Report
{
  private $data;

  public function __construct(\Scat\Service\Data $data) {
    $this->data= $data;
  }

  public function sales($span= '', $begin= null, $end= null) {
    $items= "1=1";

    if (!$begin) {
      $begin= "DATE(NOW() - INTERVAL 10 DAY)";
    } else {
      $begin= "'{$begin}'";
    }

    if (!$end) {
      $end= "DATE(NOW() + INTERVAL 1 DAY)";
    } else {
      $end= "'{$end}'";
    }

    switch ($span) {
    case 'all':
      $format= 'All';
      break;
    case 'year':
      $format= '%Y';
      break;
    case 'month':
      $format= '%Y-%m';
      break;
    case 'week':
      $format= '%X-W%v';
      break;
    case 'hour':
      $format= '%w (%a) %H:00';
      break;
    case 'day':
    default:
      $format= '%Y-%m-%d %a';
      break;
    }

    $q= "SELECT DATE_FORMAT(filled, '$format') AS span,
                SUM(taxed + untaxed) AS total,
                SUM(IF(tax_rate OR uuid, 0, taxed + untaxed)) AS resale,
                SUM(IF(uuid, tax,
                       ROUND_TO_EVEN(taxed * (tax_rate / 100), 2)))
                  AS tax,
                SUM(IF(uuid, untaxed + taxed + tax,
                       ROUND_TO_EVEN(taxed * (1 + (tax_rate / 100)), 2)
                         + untaxed))
                  AS total_taxed,
                MIN(DATE(filled)) AS raw_date,
                COUNT(*) AS transactions
           FROM (SELECT 
                        txn.uuid,
                        filled,
                        CAST(ROUND_TO_EVEN(
                          SUM(IF(txn_line.taxfree, 1, 0) *
                            IF(type = 'customer', -1, 1) * ordered *
                            sale_price(txn_line.retail_price,
                                       txn_line.discount_type,
                                       txn_line.discount)),
                          2) AS DECIMAL(9,2))
                        AS untaxed,
                        CAST(ROUND_TO_EVEN(
                          SUM(IF(txn_line.taxfree, 0, 1) *
                            IF(type = 'customer', -1, 1) * ordered *
                            sale_price(txn_line.retail_price,
                                       txn_line.discount_type,
                                       txn_line.discount)),
                          2) AS DECIMAL(9,2))
                        AS taxed,
                        tax_rate,
                        SUM(tax) AS tax
                   FROM txn
                   LEFT JOIN txn_line ON (txn.id = txn_line.txn_id)
                        JOIN item ON (txn_line.item_id = item.id)
                  WHERE filled IS NOT NULL
                    AND filled BETWEEN $begin AND $end
                    AND type = 'customer'
                    AND code NOT LIKE 'ZZ-gift%'
                    AND ($items)
                  GROUP BY txn.id
                ) t
          GROUP BY 1
          ORDER BY 1 DESC";

    $sales= $this->data->for_table('item')->raw_query($q)->find_many();

    return [ "sales" => $sales ];
  }

  public function emptyProducts() {
    return [ "products" => $this->data->factory('Product')
                             ->select('*')
                             ->select_expr('(SELECT COUNT(*)
                                               FROM item
                                              WHERE product.id = product_id AND
                                                    item.active)',
                                           'items')
                             ->where_gt('product.active', 0)
                             ->having_equal('items', 0)
                             ->order_by_asc('name')
                             ->find_many() ];
  }

  public function backorderedItems() {
    return [ "items" => $this->data->factory('Item')
                             ->select('*')
                             ->select_expr('(SELECT SUM(allocated)
                                               FROM txn_line
                                              WHERE item.id = txn_line.item_id)',
                                           'stock')
                             ->where_gt('active', 0)
                             ->where_gt('purchase_quantity', 0)
                             ->where_not_equal('is_kit', 1)
                             ->having_lt('stock', 0)
                             ->order_by_asc('code')
                             ->find_many() ];
  }
}
