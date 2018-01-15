<?
require 'scat.php';
require 'lib/catalog.php';

head("Search Catalog @ Scat", true);

$term= $_REQUEST['q'];

$products= array();

if ($term) {
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
               ->find_many();
}

?>
<script>
$(function() {

var model= {
  term: <?=json_encode($term)?>,
  products: [
<?
  /* Convert each row to an object and add a dummy departments array */
  echo join(", \n",
            array_map(function ($p) {
                        return json_encode($p->as_array(),
                                           JSON_PRETTY_PRINT|
                                           JSON_CHECK_NUMERIC);
                      },
                      $products));
?>
  ]
};

var viewModel= ko.mapping.fromJS(model);

ko.applyBindings(viewModel);

});
</script>

<?require 'ui/catalog-search.html'?>

<p data-bind="visible: !products().length && term()"
   class="alert alert-warning">
  <strong>Sorry</strong>, but we didn't find anything for your search
  parameters. Please try again.
</p>

<div class="row" data-bind="visible: products().length">
  <table class="table table-striped table-condensed">
    <tbody data-bind="foreach: products">
      <tr class="">
        <td data-bind="text: $data.brand_name"></td>
        <td>
          <a data-bind="attr: { href: 'catalog-product.php?id=' + $data.id() },
                        text: $data.name"></a>
        </td>
      </tr>
    </tbody>
  </table>
</div>
<?
foot();

