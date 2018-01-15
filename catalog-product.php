<?
require 'scat.php';
require 'lib/catalog.php';

// XXX Use Knockout to make title dynamic
head("Product @ Scat", true);

$id= (int)$_REQUEST['id'];

$product= Model::factory('Product')
            ->select('product.*')
            ->select('brand.name', 'brand_name')
            ->select_expr('(SELECT COUNT(DISTINCT variation)
                              FROM item
                              WHERE item.product_id = product.id)',
                          'variations')
            ->join('brand', array('product.brand_id', '=', 'brand.id'))
            ->find_one($id);

if (!$product) {
  die("No such product.");
}

$items= $product->items()
          ->order_by_asc('variation')
          ->order_by_desc('active')
          ->order_by_expr('IF(minimum_quantity OR stock, 0, 1)')
          ->order_by_asc('code')
          ->find_many();
?>
<script>
$(function() { 
var model= {
  product: <?=json_encode($product->as_array(),
                          JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK)?>,
  items: [
<?
  /* Convert each row to an object */
  echo join(", \n",
            array_map(function ($items) {
                        return json_encode($items->as_array(),
                                           JSON_PRETTY_PRINT|
                                           JSON_NUMERIC_CHECK);
                      },
                      $items));
?>
  ],
  showInactive: <?=array_reduce($items, function ($c, $i) { return $c + $i->active; }) ? 0 : 1?>
};

var viewModel= ko.mapping.fromJS(model);

viewModel.toggleActive= function (item) {
  Scat.api('item-update',
           { id: item.id(),
             active: item.active() ? 0 : 1 })
      .done(function (data) {
        // XXX Doesn't re-sort the items
        ko.mapping.fromJS(data.item, {}, item);
      });
}

ko.applyBindings(viewModel);
});
</script>
<div class="pull-right">
  <label>
    <input type="checkbox" data-bind="checked: showInactive">
    Show inactive
  </label>
</div>
<div class="page_header">
  <h1>
    <span data-bind="text: product.name"></span>
    <small>
      <a data-bind="attr: { href: 'catalog-department.php?id=' +
                                  product.department_id() }">
        <i class="fa fa-reply"></i>
      </a>
    </small>
  </h1>
</div>

<!-- Product description -->
<div class="row">
  <!-- XXX markdown -->
  <div class="col-sm-9" data-bind="text: product.description"></div>

  <div class="col-sm-3">
    <div class="thumbnail pull-right">
      <img width="240"
           data-bind="attr: { src: '<?=ORDURE_STATIC?>' + product.image() }">
    </div>
  </div>
<div>

<table class="table table-striped table-hover">
  <thead>
    <tr>
      <th class="col-xs-2">Item No.</th>
      <th class="col-xs-1" data-bind="visible: product.variations() > 1">
        Variation
      </th>
      <th class="col-xs-4">Description</th>
      <th class="col-xs-2">List</th>
      <th class="col-xs-1">Sale</th>
      <th class="col-xs-2 text-center">Availability</th>
      <th class="text-center">Active</th>
    </tr>
  </thead>
  <tbody data-bind="foreach: items">
    <tr data-bind="visible: $data.active() || $parent.showInactive()">
      <td>
        <a data-bind="text: $data.code,
                      attr: { href: 'item.php?id=' + $data.id() }"></a>
      </td>
      <td data-bind="visible: $parent.product.variations() > 1,
                     text: $data.variation"></td>
      <td>
        <a data-bind="text: $data.short_name() != '' ? $data.short_name
                                                     : $data.name,
                      attr: { href: 'item.php?id=' + $data.id() }"></a>
      </td>
      <td>
        <span data-bind="text: Scat.amount($data.retail_price() *
                                           $data.purchase_quantity())"></span>
        <div data-bind="visible: $data.purchase_quantity() > 1">
          <small>(<!-- ko text: $data.purchase_quantity --><!-- /ko -->
                  pieces)</small>
        </div>
      </td>
      <td class="text-primary">
        <span data-bind="text: Scat.amount($data.sale_price() *
                                           $data.purchase_quantity())"></span>
      </td>
      <td class="text-center">
        <small data-bind="visible: !$data.minimum_quantity() &&
                                   $data.stock() <= 0"
               class="text-muted">
          Special order
        </small>
        <small data-bind="visible: $data.minimum_quantity() &&
                                   $data.stock() <= 0"
               class="text-warning">
          Out of stock
        </small>
        <small data-bind="visible: $data.stock() > 0" class="text-success">
          In stock
        </small>
      </td>
      <td class="text-center">
        <a data-bind="click: $parent.toggleActive">
          <i class="fa fa-lg"
             data-bind="css: { 'fa-eye': $data.active(),
                               'fa-eye-slash': !$data.active() }"></i>
        </a>
      </td>
    </tr>
  </tbody>
</table>
<?
foot();
