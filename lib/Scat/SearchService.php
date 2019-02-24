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
    return [
      'items' => $this->searchItems($q),
      'products' => $this->searchProducts($q)
    ];
  }

  public function searchItems($q) {
    $scanner= new \OE\Lukas\Parser\QueryScanner();
    $parser= new \OE\Lukas\Parser\QueryParser($scanner);
    $parser->readString($q);
    $query= $parser->parse();

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
                                   ->where_gte('item.active', 1)
                                   ->order_by_asc('code')
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
