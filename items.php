<?
require 'scat.php';
require 'lib/item.php';

ob_start();

head("search");

$q= $_GET['q'];
?>
<div style="float: right">
 <button id="add-item">Add New Item</button>
</div>
<form method="get" action="<?=$_SERVER['PHP_SELF']?>">
<input id="autofocus" type="text" autocomplete="off" size="60" name="q" value="<?=htmlspecialchars($q)?>">
<label><input type="checkbox" value="1" name="all"<?=$_REQUEST['all']?' checked="checked"':''?>> All</label>
<input type="submit" value="Search">
</form>
<form id="add-item-form" style="display: none">
 <label>Code: <input type="text" name="code"></label>
 <br>
 <label>Name: <input type="text" name="name" size="40"></label>
 <br>
 <label>Price: <input type="text" name="retail_price" size="8"></label>
 <br>
 <input type="submit" value="Add Item">
</form>
<script>
$('#add-item').on('click', function(ev) {
  $.modal($('#add-item-form'));
});
$('#add-item-form').on('submit', function(ev) {
  ev.preventDefault();
  var data= $("#add-item-form :input").serializeArray();
  $.getJSON("api/item-add.php?callback=?",
            data,
            function (data) {
              if (data.error) {
                alert(data.error + ': ' + data.explain);
                return;
              }
              window.location.href= 'item.php?id=' + data.item.id;
            });
});
</script>
<br>
<?

if (!$q) exit;

$begin= false;

$options= 0;
if ($_REQUEST['all'])
  $options|= FIND_ALL;

list($sql_criteria, $begin) = item_terms_to_sql($db, $q, $options);

$extra= "";
if (!$begin) {
  $begin= date("Y-m-d", time() - 90*24*3600);
}
# XXX allow option to include inactive and/or deleted
if (!$_REQUEST['all'])
  $criteria[]= "(active AND NOT deleted)";

$q= "SELECT
            item.id AS meta,
            item.code Code\$item,
            item.name Name\$name,
            brand.name Brand\$brand,
            retail_price MSRP\$dollar,
            IF(item.discount_type,
               CASE item.discount_type
                 WHEN 'percentage' THEN ROUND(retail_price * ((100 - item.discount) / 100), 2)
                 WHEN 'relative' THEN (retail_price - item.discount) 
                 WHEN 'fixed' THEN (item.discount)
               END,
               NULL) Sale\$dollar,
            CASE item.discount_type
              WHEN 'percentage' THEN CONCAT(ROUND(item.discount), '% off')
              WHEN 'relative' THEN CONCAT('$', item.discount, ' off')
            END Discount\$discount,
            (SELECT SUM(allocated) FROM txn_line WHERE item = item.id) Stock\$right,
            minimum_quantity Minimum\$right,
            active Active\$bool
       FROM item
  LEFT JOIN brand ON (item.brand = brand.id)
  LEFT JOIN barcode ON (item.id = barcode.item)
      WHERE $sql_criteria
   GROUP BY item.id
   ORDER BY 2";

$r= $db->query($q)
  or die($db->error);

if ($r->num_rows == 1) {
  $row= $r->fetch_assoc();
  ob_end_clean();
  header("Location: item.php?id=" . $row['meta']);
  exit;
}
ob_end_flush();

dump_table($r);
?>
<button id="print-price-labels">Print Price Labels</button>
<script>
function updateItem(item) {
  $('.' + item.id + ' .name').text(item.name);
  $('.' + item.id + ' .brand').text(item.brand);
  $('.' + item.id + ' td:nth(4)').text(amount(item.retail_price));
  $('.' + item.id + ' td:nth(5)').text(amount(item.sale_price));
  $('.' + item.id + ' .discount').text(item.discount_label);
  $('.' + item.id + ' td:nth(7)').text(item.stock);
  $('.' + item.id + ' td:nth(8)').text(item.minimum_quantity);
  var active= parseInt(item.active);
  $('.' + item.id + ' td:nth(9) img').data('truth', active);
  if (active) {
    $('.' + item.id + ' td:nth(9) img').attr('src', 'icons/accept.png');
  } else {
    $('.' + item.id + ' td:nth(9) img').attr('src', 'icons/cross.png');
  }
}
$('tbody tr .name').editable(function(value, settings) {
  var item= $(this).closest('tr').attr('class');

  $.getJSON("api/item-update.php?callback=?",
            { item: item, name: value },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              updateItem(data.item);
            });
  return "...";
}, { event: 'click', style: 'display: inline' });
$('tbody tr .brand').editable(function(value, settings) {
  var item= $(this).closest('tr').attr('class');

  $.getJSON("api/item-update.php?callback=?",
            { item: item, brand: value },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              updateItem(data.item);
            });
  return "...";
}, { event: 'click', style: 'display: inline', type: 'select', submit: 'OK',
loadurl: 'api/brand-list.php', placeholder: '' });
$('tbody tr td:nth-child(5)').editable(function(value, settings) {
  var item= $(this).closest('tr').attr('class');

  $.getJSON("api/item-update.php?callback=?",
            { item: item, retail_price: value },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              updateItem(data.item);
            });
  return "...";
}, { event: 'click', style: 'display: inline', placeholder: '', });
$('tbody tr .discount').editable(function(value, settings) {
  var item= $(this).closest('tr').attr('class');

  $.getJSON("api/item-update.php?callback=?",
            { item: item, discount: value },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              updateItem(data.item);
            });
  return "...";
}, { event: 'click', style: 'display: inline', placeholder: '', });
$('tbody tr td:nth-child(8)').editable(function(value, settings) {
  var item= $(this).closest('tr').attr('class');

  $.getJSON("api/item-update.php?callback=?",
            { item: item, stock: value },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              updateItem(data.item);
            });
  return "...";
}, { event: 'click', style: 'display: inline' });
$('tbody tr td:nth-child(9)').editable(function(value, settings) {
  var item= $(this).closest('tr').attr('class');

  $.getJSON("api/item-update.php?callback=?",
            { item: item, minimum_quantity: value },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              updateItem(data.item);
            });
  return "...";
}, { event: 'click', style: 'display: inline' });
$('tbody').on('click', 'tr td:nth-child(10)', function(ev) {
  ev.preventDefault();
  var item= $(this).closest('tr').attr('class');
  var val= $("img", this).data('truth');

  $.getJSON("api/item-update.php?callback=?",
            { item: item, active: parseInt(val) ? 0 : 1 },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              updateItem(data.item);
            });
});
$('#print-price-labels').on('click', function(ev) {
  ev.preventDefault();
  var q= $('#autofocus').val();

  $.getJSON("print/labels-price.php?callback=?",
            { q: q },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
            });
});
</script>
<?
dump_query($q);

foot();
