<?
include '../scat.php';

$term= $_REQUEST['term'];

$products= array();

if (!$term) {
  die_jsonp([ 'error' => "Need to supply some search terms.",
              'results' => [] ]);
}

$products= Model::factory('Product')
             ->select("product.*")
             ->select("department.name", "department_name")
             ->select("department.slug", "department_slug")
             ->select("brand.name", "brand_name")
             ->select_expr('(SELECT slug
                               FROM department AS parent
                              WHERE department.parent_id = parent.id)',
                           'parent_slug')
             ->join('brand', array('product.brand_id', '=', 'brand.id'))
             ->join('department', array('product.department_id', '=',
                                        'department.id'))
             ->where_raw('MATCH(product.name, product.description)
                          AGAINST (? IN NATURAL LANGUAGE MODE)',
                          array($term))
             ->where('active', 1)
             ->find_array();

/* Select2 */
if ($_REQUEST['_type'] == 'query') {
  $data= [ 'results' => [], 'pagination' => [ 'more' => false ]];

  foreach ($products as $product) {
    $data['results'][]= [ 'id' => $product['id'], 'text' => $product['name'] ];
  }

  echo jsonp($data);
  exit;
}

/* Other queries */
echo jsonp($products);
