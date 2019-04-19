<?php
namespace Scat;

use PDO;

class SearchService
{
  private $pdo;

  public function __construct(array $config) {
    $this->pdo= new PDO($config['dsn'], $config['user'], $config['pass']);
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  }

  public function search($q) {
    $items= $products= $errors= [];
    try {
      $items= $this->searchItems($q);
      # / trips up SphinxSearch parser, but we like to use it
      $q= preg_replace('#([/])#', '\\/', $q);
      $products= $this->searchProducts($q);
    } catch (\Exception $e) {
      $errors[]= $e->getMessage();
    }
    return [
      'items' => $items,
      'products' => $products,
      'errors' => $errors,
    ];
  }

  public function searchItems($q) {
    $scanner= new \OE\Lukas\Parser\QueryScanner();
    $parser= new \OE\Lukas\Parser\QueryParser($scanner);
    $parser->readString($q);
    $query= $parser->parse();

    if (!$query) {
      $feedback= $parser->getFeedback();
      foreach ($feedback as $msg) {
        error_log($msg);
      }
      throw new \Exception($msg);
    }

    $v= new \Scat\SearchVisitor();
    $query->accept($v);

    $items= \Model::factory('Item')->select('item.*')
                                   ->left_outer_join('brand',
                                                     array('brand.id', '=',
                                                           'item.brand'))
                                   ->left_outer_join('barcode',
                                                     array('barcode.item', '=',
                                                           'item.id'))
                                   ->where_raw($v->where_clause())
                                   ->where_gte('item.active',
                                               $v->force_all ? 0 : 1)
                                   ->where_not_equal('item.deleted', 1)
                                   ->group_by('item.id')
                                   ->order_by_asc('item.code')
                                   ->find_many();

    return $items;
  }

  public function searchProducts($terms) {
    // This should rank products for which we stock items higher
    $q= "SELECT id, WEIGHT() weight
           FROM ordure
          WHERE MATCH(?)
         OPTION ranker=expr('sum(lcs*user_weight)*1000+bm25+if(items, 4000, 0)')";

    $stmt= $this->pdo->prepare($q);
    $stmt->execute(array($terms));

    $products= $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    return $products ?
           \Model::factory('Product')->where_in('product.id', $products)
                                     ->where_gte('product.active', 1)
                                     ->order_by_asc('product.name')
                                     ->find_many() : [];
  }
}
