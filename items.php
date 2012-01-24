<?
require 'scat.php';

head("search");

$q= $_GET['q'];
?>
<form method="get" action="<?=$_SERVER['PHP_SELF']?>">
<input id="focus" type="text" autocomplete="off" size="60" name="q" value="<?=htmlspecialchars($q)?>">
<label><input type="checkbox" value="1" name="all"<?=$_REQUEST['all']?' checked="checked"':''?>> All</label>
<input type="submit" value="Search">
</form>
<br>
<?

if (!$q) exit;

$terms= preg_split('/\s+/', $q);
$criteria= array();
foreach ($terms as $term) {
  $term= $db->real_escape_string($term);
  if (preg_match('/^code:(.+)/i', $term, $dbt)) {
    $criteria[]= "(item.code LIKE '{$dbt[1]}%')";
  } else {
    $criteria[]= "(item.name LIKE '%$term%'
               OR brand.name LIKE '%$term%'
               OR item.code LIKE '%$term%'
               OR barcode.code LIKE '%$term%')";
  }
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
            IF(discount_type,
               CASE discount_type
                 WHEN 'percentage' THEN ROUND(retail_price * ((100 - discount) / 100), 2)
                 WHEN 'relative' THEN (retail_price - discount) 
                 WHEN 'fixed' THEN (discount)
               END,
               NULL) Sale\$dollar,
            CASE discount_type
              WHEN 'percentage' THEN CONCAT(ROUND(discount), '% off')
              WHEN 'relative' THEN CONCAT('$', discount, ' off')
            END Discount\$discount,
            (SELECT SUM(allocated) FROM txn_line WHERE item = item.id) Stock\$right,
            minimum_quantity Minimum\$right,
            active Active\$bool
       FROM item
  LEFT JOIN brand ON (item.brand = brand.id)
  LEFT JOIN barcode ON (item.id = barcode.item)
      WHERE " . join(' AND ', $criteria) . "
   GROUP BY item.id";

$r= $db->query($q)
  or die($db->error);

dump_table($r);
?>
<script>
function updateItem(item) {
  $('.' + item.id + ' .name').text(item.name);
  $('.' + item.id + ' .brand').text(item.brand);
  $('.' + item.id + ' td:nth(4)').text(item.retail_price);
  $('.' + item.id + ' td:nth(5)').text(item.sale_price);
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
}, { event: 'dblclick', style: 'display: inline' });
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
}, { event: 'dblclick', style: 'display: inline', type: 'select', submit: 'OK',
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
}, { event: 'dblclick', style: 'display: inline', placeholder: '', });
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
}, { event: 'dblclick', style: 'display: inline', placeholder: '', });
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
}, { event: 'dblclick', style: 'display: inline' });
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
}, { event: 'dblclick', style: 'display: inline' });
$('tbody').on('dblclick', 'tr td:nth-child(10)', function(ev) {
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
</script>
<?
dump_query($q);

foot();
