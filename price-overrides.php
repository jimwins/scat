<?
require 'scat.php';
require 'lib/txn.php';

head("Price Overrides @ Scat", true);
?>
<table class="table table-striped table-hover sortable">
  <thead>
    <tr>
      <th>Pattern</th>
      <th>Minimum</th>
      <th>Discount</th>
      <th>Expires</th>
      <th>In Stock Only</th>
      <th></th>
    </tr>
  </thead>
  <tfoot>
    <tr>
      <td colspan="5">
        <button role="button" class="btn btn-primary"
                data-bind="click: editOverride">
          Add Override
        </button>
      </td>
    </tr>
  </tfoot>
  <tbody data-bind="foreach: overrides">
    <tr>
      <td><a data-bind="text: $data.pattern,
                     attr: { href : $parent.getSearchURL($data) }">MXG%</a></td>
      <td data-bind="text: $data.minimum_quantity">12</td>
      <td data-bind="text: $parent.formatDiscount($data.discount_type,
                                                  $data.discount)">+10%</td>
      <td data-bind="text: $data.expires ? $data.expires
                                         : 'Never'">Never</td>
      <td data-bind="text: $data.in_stock != 0 ? 'Yes' : 'No'">Maybe</td>
      <td>
        <button role="button" class="btn btn-xs btn-default"
                data-bind="click: $parent.editOverride">
          <i class="fa fa-pencil-alt"></i>
        </button>
        <button role="button" class="btn btn-xs btn-default"
                data-bind="click: $parent.deleteOverride">
          <i class="far fa-trash-alt"></i>
        </button>
      </td>
    </tr>
  </tbody>
</table>
<script>
$(function() {

  var model= {
    overrides: []
  };

  var viewModel= ko.mapping.fromJS(model);

  viewModel.formatDiscount= function(discount_type, discount) {
    var val= parseFloat(discount).toFixed(2);
    switch (discount_type) {
      case 'percentage':
        return val + '%';

      case 'additional_percentage':
        return '+' + val + '%';

      case 'relative':
        return '-' + val;

      case 'fixed':
        return Scat.amount(val);
    }
    return "???";
  }

  viewModel.getSearchURL= function(data) {
    var search= '';
    switch (data.pattern_type) {
    case 'rlike':
      search= 're:' + data.pattern;
      break;
    case 'like':
      search= data.pattern;
      break;
    case 'product':
      search= 'product:' + data.pattern;
      break;
    }

    return 'items.php?search=' + search;
  }

  viewModel.editOverride= function(place, ev) {
    Scat.dialog('price-override').done(function (html) {
      var panel= $(html);

      var override= place.id ? place :
                               {
                                 id: 0,
                                 pattern: '', pattern_type: 'like',
                                 minimum_quantity: 1,
                                 discount_type: 'fixed', discount: 0,
                                 expires: null, in_stock: 0
                               };
      override.error= '';

      panel.on('hidden.bs.modal', function() {
        $(this).remove();
      });

      panel.on('shown.bs.modal', function() {
        $('#expires-datepicker').datepicker({
          format: 'yyyy-mm-dd',
          todayHighlight: true,
          clearBtn: true,
          autoclose: true,
        });
      });

      overrideModel= ko.mapping.fromJS(override);

      overrideModel.formatted_discount= ko.pureComputed({
        read: function() {
          return viewModel.formatDiscount(this.discount_type(),
                                          this.discount());
        },
        write: function (value) {
          if ((m= value.match(/(\+|\-|\$)?(\d+(?:\.\d+)?)(\/|%)?/))) {
            this.discount(m[2]);
            if (m[1] == '+' && (m[3] == '%' || m[3] == '/')) {
              this.discount_type('additional_percentage');
            } else if (m[3] == '%' || m[3] == '/') {
              this.discount_type('percentage');
            } else if (m[1] == '-') {
              this.discount_type('relative');
            } else {
              this.discount_type('fixed');
            }
          } else {
            this.error("Don't understand discount!");
          }
        },
        owner: overrideModel
      });

      overrideModel.saveOverride= function(place, ev) {
        var override= ko.mapping.toJS(overrideModel);
        delete override.error;

        Scat.api(override.id ? 'override-update' : 'override-add', override)
            .done(function (data) {
              $(place).closest('.modal').modal('hide');
              if (!override.id) {
                viewModel.overrides.push(data);
              } else {
                var idx= viewModel.overrides()
                                  .findIndex(function (o) {
                                               return o.id == override.id
                                            });
                viewModel.overrides.splice(idx, 1);
                viewModel.overrides.splice(idx, 0, data);
              }
            });
      }

      ko.applyBindings(overrideModel, panel[0]);

      panel.appendTo($('body')).modal();
    });
  }

  viewModel.deleteOverride= function(place, ev) {
    if (confirm("Are you sure you want to delete this override?")) {
      Scat.api('override-delete', { id: place.id })
          .done(function (data) {
            viewModel.overrides.remove(function (item) {
                                         return item.id == place.id
                                      });
          });
    }
  }

  ko.applyBindings(viewModel, document.getElementById('scat-page'));

  Scat.api('override-list')
      .done(function (data) {
        viewModel.overrides(data);
      });
});
</script>
<?
foot();
?>

