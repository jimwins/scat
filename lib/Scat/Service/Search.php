<?php
namespace Scat\Service;

class Search
{
  private $data;
  private $pdo;
  private $insert;

  public function __construct(Config $config, Data $data) {
    $this->data= $data;

    $dsn= $config->get('search.dsn') ?? 'mysql:host=search;port=9306';

    $username= $config->get('search.username');
    $password= $config->get('search.password');

    $this->pdo= new \PDO($dsn, $username, $password);
    $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
  }

  public function search($q, $limit= null) {
    $items= $brands= $products= $errors= [];

    try {
      $items= $this->searchItems($q, $limit);
    } catch (\Exception $e) {
      $errors[]= $e->getMessage();
    }

    try {
      $brands= $this->searchBrands($q);
    } catch (\Exception $e) {
      $errors[]= $e->getMessage();
    }

    try {
      $products= $this->searchProducts($q, $limit);
    } catch (\Exception $e) {
      $errors[]= $e->getMessage();
    }

    return [
      'brands' => $brands,
      'items' => $items,
      'products' => $products,
      'errors' => $errors,
    ];
  }

  public function buildSearchItemsWhere($q) {
    $scanner= new \OE\Lukas\Parser\QueryScanner();
    $parser= new \OE\Lukas\Parser\QueryParser($scanner);
    $parser->readString($q);
    $query= $parser->parse();

    if (!$query) {
      $feedback= $parser->getFeedback();
      throw new \Exception($feedback[0]);
    }

    $v= new \Scat\SearchVisitor();
    $query->accept($v);

    return [ $v->where_clause(), $v->force_all ];
  }

  public function buildSearchItems($q, $limit= null) {
    list($where, $force_all)= $this->buildSearchItemsWhere($q);

    $items= $this->data->factory('Item')->select('item.*')
                                   ->select_expr('COUNT(*) OVER()', 'records')
                                   // TODO calculate stock for kits
                                   ->select_expr('IFNULL((SELECT SUM(allocated)
                                                     FROM txn_line
                                                    WHERE txn_line.item_id =
                                                           item.id),
                                                         0)',
                                                    'stock')
                                   ->select('brand.name', 'brand_name')
                                   ->left_outer_join('product',
                                                     array('product.id', '=',
                                                           'item.product_id'))
                                   ->left_outer_join('brand',
                                                     array('brand.id', '=',
                                                           'product.brand_id'))
                                   ->left_outer_join('department',
                                                     array('department.id', '=',
                                                           'product.department_id'))
                                   ->left_outer_join('barcode',
                                                     array('barcode.item_id',
                                                           '=', 'item.id'))
                                   ->where_raw($where)
                                   ->where_gte('item.active', $force_all ? 0 : 1)
                                   ->where_not_equal('item.deleted', 1)
                                   ->group_by('item.id')
                                   ->order_by_expr('!(minimum_quantity > 0 OR stock != 0), item.code');
    if ($limit) {
      $items= $items->limit($limit);
    }

    return $items;
  }

  public function searchItems($q, $limit= null) {
    return $this->buildSearchItems($q, $limit)->find_many();
  }

  public function searchBrands($q) {
    return $this->data->factory('Brand')
            ->where_raw('MATCH (name, slug) AGAINST (? IN NATURAL LANGUAGE MODE)', [ $q ])
            ->where('active', 1)
            ->find_many();
  }

  public function searchProducts($terms, $limit) {
    # / trips up SphinxSearch parser, but we like to use it
    $terms= preg_replace('#([/])#', '\\/', $terms);

    // This should rank products for which we stock items higher
    $q= "SELECT id, WEIGHT() weight
           FROM scat
          WHERE MATCH(?)
         OPTION ranker=expr('sum(lcs*user_weight)*1000+bm25+if(items, 4000, 0)')";

    $stmt= $this->pdo->prepare($q);
    $stmt->execute([ $terms ]);

    $products= $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);

    return $products ?
           $this->data->factory('Product')->where_in('product.id', $products)
                                     ->where_gte('product.active', 1)
                                     ->order_by_asc('product.name')
                                     ->find_many() : [];
  }

  public function flush() {
    return $this->pdo->query("DELETE FROM scat WHERE id > 0");
  }

  public function indexProduct($product) {
    if (!isset($this->insert)) {
      $query= "REPLACE INTO scat (id, group_id, items, is_deleted, date_added,
                                  title, content, brand_name)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
      $this->insert= $this->pdo->prepare($query);
    }

    $this->insert->execute([
      $product->id,
      $product->department_id,
      $product->stocked() ?: 0,
      $product->active ? 0 : 1,
      strtotime($product->added),
      $product->name,
      $product->description,
      $product->brand_name() ?: '',
    ]);

    return $this->insert->rowCount();
  }

  public function suggestTerm($term) {
    $q= "CALL SUGGEST(?,?)";
    $stmt= $this->pdo->prepare($q);
    $stmt->execute([ $term, 'scat']);
    $res= $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    return $res ? $res[0] : null;
  }
}
