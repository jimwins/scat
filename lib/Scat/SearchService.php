<?php
namespace Scat;

class SearchService
{
  public function __construct() {
  }

  public function search($q) {
    $items= \Model::factory('Item')->select('item.*')
                                   ->where('code', $q)
                                   ->where_gte('active', 1)
                                   ->order_by_asc('code')
                                   ->find_many();
    return [ 'items' => $items ];
  }
}
