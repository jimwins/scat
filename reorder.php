<?
include 'scat.php';

head("Reorder @ Scat", true);

$extra= $extra_field= $extra_field_name= '';
$code_field= "code";

$all= (int)$_REQUEST['all'];

$vendor= (int)$_REQUEST['vendor'];
if ($vendor > 0) {
  $code_field= "(SELECT code FROM vendor_item WHERE vendor = $vendor AND item = item.id AND vendor_item.active LIMIT 1)";
  $extra= "AND EXISTS (SELECT id
                         FROM vendor_item
                        WHERE vendor = $vendor
                          AND item = item.id
                          AND vendor_item.active)";
  $extra_field= "(SELECT MIN(IF(promo_quantity, promo_quantity,
                                purchase_quantity))
                    FROM vendor_item
                   WHERE item = item.id
                     AND vendor = $vendor
                     AND vendor_item.active)
                  AS minimum_order_quantity,
                 (SELECT MIN(IF(promo_price, promo_price, net_price))
                    FROM vendor_item
                    JOIN person ON vendor_item.vendor = person.id
                  WHERE item = item.id
                    AND vendor = $vendor
                    AND vendor_item.active)
                  AS cost,
                 (SELECT MIN(IF(promo_price, promo_price, net_price)
                             * ((100 - vendor_rebate) / 100))
                    FROM vendor_item
                    JOIN person ON vendor_item.vendor = person.id
                  WHERE item = item.id
                    AND NOT special_order
                    AND vendor = $vendor
                    AND vendor_item.active) -
                 (SELECT MIN(IF(promo_price, promo_price, net_price)
                             * ((100 - vendor_rebate) / 100))
                    FROM vendor_item
                    JOIN person ON vendor_item.vendor = person.id
                   WHERE item = item.id
                     AND NOT special_order
                     AND vendor != $vendor
                     AND vendor_item.active)
                 cheapest, ";
  $extra_field_name= "minimum_order_quantity, cheapest, cost,";
} else if ($vendor < 0) {
  // No vendor
  $extra= "AND NOT EXISTS (SELECT id
                             FROM vendor_item
                            WHERE item = item.id
                              AND vendor_item.active)";
}

$code= $_REQUEST['code'];
if ($code) {
  $extra.= " AND code LIKE '" . $db->escape($code) . "%'";
}
?>
<form class="form-inline" method="get" action="<?=$_SERVER['PHP_SELF']?>">
  <div class="form-group">
    <label class="sr-only" for="vendor"></label>
    <select class="form-control" name="vendor">
      <option value="">All vendors</option>
      <option value="-1" <?if ($vendor == -1) echo 'selected'?>>
        No vendor
      </option>
<?
$q= "SELECT id, company FROM person WHERE role = 'vendor' ORDER BY company";
$r= $db->query($q);

while ($row= $r->fetch_assoc()) {
  echo '<option value="', $row['id'], '"',
       ($row['id'] == $vendor) ? ' selected' : '',
       '>',
       ashtml($row['company']),
       '</option>';
}
?>
    </select>
  </div>
  <div class="form-group">
    <label class="sr-only" for="code">Code</label>
    <input type="text" class="form-control" name="code" placeholder="Code"
           value="<?=ashtml($code)?>">
  </div>
  <div class="checkbox">
    <label>
      <input type="checkbox" name="all" value="1"
       <?=($all ? 'checked="checked"' : '')?>>
      All?
    </label>
  </div>
  <button type="submit" class="btn btn-primary">Limit</button>
</form>

<?
$criteria= ($all ? '1=1'
                 : '(ordered IS NULL OR NOT ordered)
                    AND IFNULL(stock, 0) < minimum_quantity');
$q= "SELECT id, code, name, stock,
            minimum_quantity, last3months,
            $extra_field_name
            order_quantity
       FROM (SELECT item.id,
                    $code_field code,
                    name,
                    SUM(allocated) stock,
                    minimum_quantity,
                    (SELECT -1 * SUM(allocated)
                       FROM txn_line JOIN txn ON (txn = txn.id)
                      WHERE type = 'customer'
                        AND txn_line.item = item.id
                        AND filled > NOW() - INTERVAL 3 MONTH)
                    AS last3months,
                    (SELECT SUM(ordered - allocated)
                       FROM txn_line JOIN txn ON (txn = txn.id)
                      WHERE type = 'vendor'
                        AND txn_line.item = item.id
                        AND created > NOW() - INTERVAL 12 MONTH)
                    AS ordered,
                    $extra_field
                    IF(minimum_quantity > minimum_quantity - SUM(allocated),
                       minimum_quantity,
                       minimum_quantity - IFNULL(SUM(allocated), 0))
                      AS order_quantity
               FROM item
               LEFT JOIN txn_line ON (item = item.id)
              WHERE purchase_quantity
                AND item.active AND NOT item.deleted
                $extra
              GROUP BY item.id
              ORDER BY code) t
       WHERE $criteria
       ORDER BY code
      ";

$r= $db->query($q)
  or die_query($db, $q);

$results= array();
while (($row= $r->fetch_assoc())) {
  $results[]= $row;
}

?>
<form class="form-inline" data-bind="submit: createOrder">
  <div class="pull-right">
    <button class="btn btn-default"
            data-bind="click: zero">Zero</button>
    <button class="btn btn-default"
            data-bind="click: optimize">Optimize</button>
  </div>
  <table class="table table-condensed table-striped"
         data-bind="if: results().length">
    <thead>
      <tr>
        <th class="">Code</th>
        <th class="">Name</th>
        <th width="5%" class="text-center">Stock</th>
        <th width="5%" class="text-center">Min</th>
        <th width="5%" class="text-center">Last 3</th>
        <th width="5%" class="text-center">MOQ</th>
        <th width="5%" class="text-center">Best?</th>
        <th class=""></th>
      </tr>
    </thead>

    <tfoot>
      <tr>
        <td colspan="6">
          <button role="button" class="btn btn-default"
                  data-format="tsv"
                  data-bind="click: download">
            Download TSV
          </button>
          <button role="button" class="btn btn-default"
                  data-format="xls"
                  data-bind="click: download">
            Download XLS
          </button>
          <button role="submit" class="btn btn-primary"
                  data-bind="enable: readyToSubmit">
            Create Order
          </button>
        </td>
        <td colspan="2" align="right">
         <big>
           <strong>Total: </strong> &nbsp; 
           <span data-bind="text: Scat.amount(orderTotal())"></span>
         </big>
        </td>
      </tr>
    </tfoot>

    <tbody data-bind="foreach: results" id="search-results">
      <tr data-bind="click: $parent.selectRow">
        <td>
          <a data-bind="text: $data.code,
                        attr: { href: 'item.php?id=' + $data.id }"></a>
        </td>
        <td data-bind="text: $data.name"></td>
        <td align="center" data-bind="text: $data.stock"></td>
        <td align="center" data-bind="text: $data.minimum_quantity"></td>
        <td align="center" data-bind="text: $data.last3months"></td>
        <td align="center" data-bind="text: $data.minimum_order_quantity"></td>
        <td align="center">
          <i class="far"
             data-bind="css: { 'fa-check-square' : $data.cheapest < 0 ||
                                                   $data.cheapest === null,
                               'fa-minus-square' : $data.cheapest == 0,
                               'fa-square' : $data.cheapest > 0 }"></i>
        </td>
        <td>
          <div class="input-group">
            <span class="input-group-btn">
              <button class="btn btn-default btn-sm" type="button"
                      data-bind="click: $parent.changeQuantity" data-value="-1">
                <i class="fa fa-angle-left"></i>
              </button>
            </span>
            <input type="text"
                   class="form-control input-sm text-center mousetrap" size="3"
                   data-bind="textInput: $data.order_quantity">
            <span class="input-group-btn">
              <button class="btn btn-default btn-sm" type="button"
                      data-bind="click: $parent.changeQuantity" data-value="1">
                <i class="fa fa-angle-right"></i>
              </button>
            </span>
          </div>
        </td>
      </tr>
    </tbody>
  </table>
</form>
<form id="post-tsv" style="display: none"
      method="post" action="api/encode-tsv.php">
<textarea id="file" name="file"></textarea>
</form>
<form id="post-xls" style="display: none"
      method="post" action="api/encode-xls.php">
<textarea id="file" name="file"></textarea>
</form>
<script>
$(function() {
  function PageModel() {
    var self= this;

    var i= 0;
    self.results= ko.observableArray(
                   $.map(<?=json_encode($results)?>,
                         function (a) {
                           a.idx= i++;
                           a.order_quantity= ko.observable(a.order_quantity);
                           return a;
                         }));
    self.activeRow= ko.observable(null);

    self.activeRow.subscribe(function(idx) {
      var w= $(window);
      var row= $('#search-results').find('tr')
                                   .removeClass('active')
                                   .eq(idx)
                                   .addClass('active')
                                   .find('input').select().focus();
      if (row.length) {
        $('html,body').animate({ scrollTop: row.offset().top - w.height()/2 },
                               250);
      }
    });

    self.changeQuantity= function (item, event) {
      item.order_quantity(Number(item.order_quantity()) +
                          $(event.currentTarget).data('value'));
    }
    self.selectRow= function (item, event) {
      self.activeRow(item.idx);
      return true; // allow click to continue to buttons and links
    }

    self.readyToSubmit= ko.computed(function() {
      return true;
    });

    self.orderTotal= ko.computed(function() {
      var total= 0.00;
      $.each(self.results(), function (i, row) {
        if (parseInt(row.order_quantity())) {
          total= total + (parseInt(row.order_quantity()) * row.cost);
        }
      });
      return total;
    });

    self.createOrder= function (form) {
      var order= [];
      $.each(self.results(), function (i, row) {
        if (parseInt(row.order_quantity())) {
          order.push([ row.id, row.order_quantity() ]);
        }
      });

      Scat.api("txn-create", { type: 'vendor', person: <?=$vendor?> })
          .done(function (data) {
            Scat.api("txn-add-items", { txn: data.txn.id, items: order },
                     { method: 'POST' })
                .done(function(data) {
                  window.location= './?id=' + data.txn.id;
                });
          });
    }

    self.download= function (view, ev) {
      var format= $(ev.target).data('format');
      var tsv= "code\tqty\r\n";
      $.each(self.results(), function (i, row) {
        if (parseInt(row.order_quantity()) > 0) {
          tsv += row.code + "\t" + row.order_quantity() + "\r\n";
        }
      });
      $("#post-" + format + " #file").val(tsv);
      $("#post-" + format).submit();
    }

    self.optimize= function (form) {
      $.each(self.results(), function (i, item) {
        item.order_quantity(
          (item.cheapest <= 0 || item.cheapest === null) ?
          Math.max(
            item.minimum_order_quantity,
            item.minimum_quantity > item.minimum_quantity - item.stock ?
              item.minimum_quantity :
              item.minimum_quantity - item.stock
          ) :
          0
        );
      });
    }

    self.zero= function (form) {
      $.each(self.results(), function (i, item) {
        item.order_quantity(0);
      });
    }
  }

  var pageModel= new PageModel();
  ko.applyBindings(pageModel, document.getElementById('scat-page'));

  // Set first row active */
  pageModel.activeRow(0);

  Mousetrap.bind(['left', 'right', 'up', 'down', 'h', 'j', 'k', 'l', 'x', 'tab', 'enter', 'space' ],
                 function(ev, combo) {
    var idx= pageModel.activeRow();
    var item= (pageModel.results())[idx];
    if (idx < 0) {
      console.log("Couldn't find active row!");
      return true;
    }

    if (combo == 'left' || combo == 'h') {
      // subtract one
      item.order_quantity(Number(item.order_quantity()) - 1);
      return false;
    }
    if (combo == 'right' || combo == 'l') {
      // add one
      item.order_quantity(Number(item.order_quantity()) + 1);
      return false;
    }
    if (combo == 'up' || combo == 'k') {
      // previous one
      if (idx > 0) {
        pageModel.activeRow(idx - 1);
      }
      return false;
    }
    if (combo == 'down' || combo == 'j') {
      // next one
      if (idx < pageModel.results().length - 1) {
        pageModel.activeRow(idx + 1);
      }
      return false;
    }
    if (combo == 'x') {
      // zero, mark done and move on
      item.order_quantity(0);
      if (idx < pageModel.results().length - 1) {
        pageModel.activeRow(idx + 1);
      }
      return false;
    }
    if (combo == 'tab' || combo == 'space' || combo == 'enter') {
      // mark done and move on
      if (idx < pageModel.results().length - 1) {
        pageModel.activeRow(idx + 1);
      }
      return false;
    }
  });
});
</script>
<?

foot();
