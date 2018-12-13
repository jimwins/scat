<?
include '../scat.php';

$term= $_REQUEST['term'];

$products= array();

if (!$term) {
  die_jsonp([ 'error' => "Need to supply some search terms.",
              'results' => [] ]);
}

$brands= Model::factory('Brand')
           ->where_raw('name LIKE ? OR slug LIKE ?', [ "$term%", "$term%" ])
#           ->where('active', 1)
           ->find_array();

/* Select2 */
if ($_REQUEST['_type'] == 'query') {
  $data= [ 'results' => [], 'pagination' => [ 'more' => false ]];

  foreach ($brands as $brand) {
    $data['results'][]= [ 'id' => $brand['id'], 'text' => $brand['name'] ];
  }

  echo jsonp($data);
  exit;
}

/* Other queries */
echo jsonp($products);
