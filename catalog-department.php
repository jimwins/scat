<?
require 'scat.php';
require 'lib/catalog.php';

// XXX Use Knockout to make title dynamic
head("Department @ Scat", true);

$id= (int)$_REQUEST['id'];

$department= Model::factory('Department')
               ->find_one($id);

if (!$department) {
  die("No such department.");
}

$products= Model::factory('Product')
             ->select('product.*')
             ->select('brand.name', 'brand_name')
             ->select_expr('(SELECT IFNULL(SUM(allocated),0) +
                                    SUM(minimum_quantity)
                               FROM item
                               LEFT JOIN txn_line ON txn_line.item = item.id
                              WHERE item.product_id = product.id)',
                           'stocked')
             ->join('brand', array('product.brand_id', '=', 'brand.id'))
             ->where('department_id', $id)
             ->order_by_desc('active')
             // need to join brand to sort by brand name
             ->order_by_asc('brand.name')
             ->order_by_asc('product.name')
             ->find_many();
?>
<script>
$(function() { 
var model= {
  department: <?=json_encode($department->as_array(), JSON_PRETTY_PRINT)?>,
  products: [
<?
  /* Convert each row to an object */
  echo join(", \n",
            array_map(function ($product) {
                      return json_encode($product->as_array(),
                                         JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK);
                      },
                      $products));
?>
  ]
};

var viewModel= ko.mapping.fromJS(model);

viewModel.toggleActive= function (product) {
  Scat.api('product-update',
           { id: product.id(),
             active: product.active() == '1' ? 0 : 1 })
      .done(function (data) {
        // XXX Doesn't re-sort the products
        ko.mapping.fromJS(data, {}, product);
      });
}

ko.applyBindings(viewModel);

});
</script>
<div class="page_header">
  <h1>
    <span data-bind="text: department.name"></span>
    <small data-bind="if: department.parent_id()">
      <a data-bind="attr: { href: 'catalog-departments.php?id=' +
                                  department.parent_id() }">
        <i class="fa fa-reply"></i>
      </a>
    </small>
  </h1>
</div>

<?require 'ui/catalog-search.html'?>

<table class="table table-striped table-hover">
  <thead>
    <tr>
      <th>Brand</th>
      <th>Product</th>
      <th class="text-center">Available in Store</th>
      <th class="text-center">Active</th>
    </tr>
  </thead>
  <tbody data-bind="foreach: products">
    <tr>
      <td data-bind="text: $data.brand_name"></td>
      <td>
       <a data-bind="text: $data.name,
                     attr: { href: 'catalog-product.php?id=' + $data.id() }"></a>
      </td>
      <td class="text-center">
        <i class="fa fa-lg"
           data-bind="css: { 'fa-thumbs-up text-success': $data.stocked(),
                             'fa-thumbs-down text-muted': !$data.stocked() }"></i>
      </td>
      <td class="text-center">
        <a data-bind="click: $parent.toggleActive">
          <i class="fa fa-lg"
             data-bind="css: { 'fa-eye': $data.active() == '1',
                               'fa-eye-slash': $data.active() == '0' }"></i>
        </a>
      </td>
    </tr>
  </tbody>
</table>
<?
foot();
