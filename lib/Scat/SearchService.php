<?php
namespace Scat;

use OE\Lukas\Parser\QueryParser;
use OE\Lukas\Visitor\QueryItemPrinter;

class SearchService
{
  public function __construct() {
  }

  public function search($q) {
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

    return [ 'items' => $items ];
  }
}
