<?
require 'scat.php';

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

viewModel.toggleProductActive= function () {
  Scat.api('product-update',
           { id: viewModel.product.id(),
             active: viewModel.product.active() == '1' ? 0 : 1 })
      .done(function (data) {
        ko.mapping.fromJS(data, {}, viewModel.product);
      });
}

viewModel.editProduct= function (self) {
  Scat.dialog('product').done(function (html) {
    var panel= $(html);

    panel.on('hidden.bs.modal', function() {
      $(this).remove();
    });

    var product= ko.mapping.toJS(self.product);

    product.departments= [];
    product.brands= [];

    var panelModel= ko.mapping.fromJS(product);

    /* Load departments */
    Scat.api('department-find', { levels: 2 })
        .done(function (data) {
          ko.mapping.fromJS(data, {}, panelModel.departments);
          // make sure correct selection is made
          panelModel.department_id.valueHasMutated();
        });

    /* Load brands */
    Scat.api('brand-list', { verbose: 1 })
        .done(function (data) {
          ko.mapping.fromJS(data, {}, panelModel.brands);
          // make sure correct selection is made
          panelModel.brand_id.valueHasMutated();
        });

    panelModel.saveProduct= function(place, ev) {
      var product= ko.mapping.toJS(panelModel); /* XXX */
      delete product.departments;
      delete product.brands;

      Scat.api('product-update', product)
          .done(function (data) {
            $(place).closest('.modal').modal('hide');
            ko.mapping.fromJS(data, {}, viewModel.product);
          });
    }

    panelModel.generateSlug= function(place, ev) {
      Scat.api('generate-slug',
               { brand: panelModel.brand_id(), name: panelModel.name() })
          .done(function (data) {
            panelModel.slug(data.slug);
          })
    }

    panelModel.selectedDepartment= ko.computed({
      read: function () {
        return this.department_id();
      },
      write: function (value) {
        if (typeof value != 'undefined' && value != '') {
          this.department_id(value);
        }
      },
      owner: panelModel
    }).extend({ notify: 'always' });

    panelModel.selectedBrand= ko.computed({
      read: function () {
        return this.brand_id();
      },
      write: function (value) {
        if (typeof value != 'undefined' && value != '') {
          this.brand_id(value);
        }
      },
      owner: panelModel
    }).extend({ notify: 'always' });

    var uploaderOptions= {
      name: 'src',
      postUrl: function() { return 'api/image-add.php' },
      /* Progress */
      onClientLoadStart: function (e, file, response) {
        $('#upload-button')
          .html('<i class="fa fa-spinner fa-spin"></i> Reading...');
      },
      onClientLoadEnd: function (e, file, response) {
        $('#upload-button')
          .html('<i class="fa fa-spinner fa-spin"></i> Preparing...');
      },
      onServerLoadStart: function (e, file, response) {
        $('#upload-button')
          .html('<i class="fa fa-spinner fa-spin"></i> Uploading...');
      },
      onServerLoad: function (e, file, response) {
        $('#upload-button')
          .html('<i class="fa fa-spinner fa-spin"></i> Processing...');
      },
      onSuccess: function(e, file, response) {
        data= $.parseJSON(response);
        if (data.error) {
          Scat.alert(data);
          return;
        }
        panelModel.image('/i/st/' + data.uuid + '.jpg');
        $('#upload-button')
          .html('Upload');
      },
      onServerError: function(e, file) {
        Scat.alert("File upload failed.");
        $('#upload-button')
          .html('Upload');
      },
    };
    panel.html5Uploader(uploaderOptions);
    $('#image-file', panel).html5Uploader(uploaderOptions);

    panel.bind("drop", function (e) {
      var items= e.originalEvent.dataTransfer.items;
      if (e.originalEvent.dataTransfer.files.length) return;
      for (var i= 0; i < items.length; i++) {
        if (items[i].kind == 'string' && items[i].type == 'text/uri-list') {
          items[i].getAsString(function (s) {
            Scat.api('image-add', { url: s })
                .done(function (data) {
                  panelModel.image('/i/st/' + data.uuid + '.jpg');
                });
          });
        }
      }
      return false;
    });

    ko.applyBindings(panelModel, panel[0]);
    panel.appendTo($('body')).modal();
  });
}

ko.applyBindings(viewModel);
});
</script>

<div class="pull-right well text-center">
  <label>
    <input type="checkbox" data-bind="checked: showInactive">
    Show inactive
  </label>
  <div>
    <button class="btn btn-primary"
            data-bind="click: editProduct">Edit</button>
  </div>
  <div>
    <button class="btn btn-xs btn-default"
            data-bind="click: toggleProductActive,
                       text: product.active() == '1' ? 'Active' : 'Inactive',
                       css: { 'btn-danger' : product.active() != '1' }">
    </button>
  </div>
</div>

<?require 'ui/catalog-search.html'?>

<div class="page_header">
  <h1>
    <span data-bind="text: product.name"></span>
    <small>
      <span data-bind="text: product.brand_name"></span>
      <a data-bind="attr: { href: 'catalog-department.php?id=' +
                                  product.department_id() }">
        <i class="fa fa-reply"></i>
      </a>
    </small>
  </h1>
</div>

<!-- Product description -->
<div class="row">
  <div class="col-sm-9" data-bind="html: marked(product.description().replace(/{{\s*@STATIC\s*}}/, '<?=ORDURE_STATIC?>'))"></div>

  <div class="col-sm-3" data-bind="if: product.image">
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
