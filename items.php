<?
require 'scat.php';

head("search");

$q= $_GET['q'];
?>
<form method="get" action="<?=$_SERVER['PHP_SELF']?>">
<input id="focus" type="text" autocomplete="off" name="q" value="<?=htmlspecialchars($q)?>">
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
            minimum_quantity Minimum\$right
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
$('tbody tr .name').editable(function(value, settings) {
  var item= $(this).closest('tr').attr('class');

  $.getJSON("api/item-update.php?callback=?",
            { item: item, name: value },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              $('.' + data.item.id + ' .name').text(data.item.name);
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
              $('.' + data.item.id + ' .name').text(data.item.name);
              $('.' + data.item.id + ' .brand').text(data.item.brand);
            });
  return "...";
}, { event: 'dblclick', style: 'display: inline', type: 'select', submit: 'OK',
     loadurl: 'api/brand-list.php' });
$('tbody tr .discount').editable(function(value, settings) {
  var item= $(this).closest('tr').attr('class');

  $.getJSON("api/item-update.php?callback=?",
            { item: item, discount: value },
            function (data) {
              if (data.error) {
                $.modal(data.error);
                return;
              }
              $('.' + data.item.id + ' .name').text(data.item.name);
              $('.' + data.item.id + ' .brand').text(data.item.brand);
              $('.' + data.item.id + ' td:nth(4)').text(data.item.retail_price);
              $('.' + data.item.id + ' td:nth(5)').text(data.item.sale_price);
              $('.' + data.item.id + ' .discount').text(data.item.discount_label);
            });
  return "...";
}, { event: 'dblclick', style: 'display: inline', placeholder: '', });
</script>
<?
dump_query($q);

foot();
