<?
require 'scat.php';

head("transaction");

$type= $_REQUEST['type'];
$number= (int)$_REQUEST['number'];

if (!$type || !$number) die("no transaction specified.");

switch ($type) {
case 'vendor';
?>
<script>
$.expr[":"].match = function(obj, index, meta, stack){
  return (obj.textContent || obj.innerText || $(obj).text() || "") == meta[3];
}
$(function() {
  $('#receive #search').focus()
  $('#receive .submit').click(function() {
    $('#receive .error').hide(); // hide old error messages
    var q = $("#receive #search").val();
    $.ajax({
      url: "txn-item-receive.php",
      dataType: "json",
      data: ({ type: "<?=$type?>", number: "<?=$number?>", search: q }),
      success: function(data) {
        if (data.error) {
          $("#receive .error").html("<p>" + data.error + "</p>");
          $("#receive .error").show();
        } else {
          // update table
          var row= $(".sortable td.num:match(" + data.line + ")").parent()
          $(row).children(":eq(6)").text(data.ordered)
          $(row).children(":eq(7)").text(data.shipped)
          $(row).children(":eq(8)").text(data.allocated)
          $("#receive #search").val("");
        }
      }
    });
    return false;
  });
});
</script>
<form id="receive" action="txn-item-receive.php" method="post">
 <div class="error" style="display: none"></div>
 <input id="type" type="hidden" name="type" value="<?=ashtml($type)?>">
 <input id="number" type="hidden" name="number" value="<?=ashtml($number)?>">
 <input id="search" type="text" name="search">
 <input class="submit" type="submit" name="Receive">
</form>
<?
  break;
}


$type= $db->real_escape_string($type);

$q= "SELECT
            line AS `#\$num`,
            item.code Code\$item,
            item.name Name,
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
            END Discount,
            ordered as Ordered,
            shipped as Shipped,
            allocated as Allocated
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
       JOIN item ON (txn_line.item = item.id)
      WHERE number = '$number' AND type = '$type'
      ORDER BY line ASC";

dump_table($db->query($q));
dump_query($q);

switch ($type) {
case 'vendor';
?>
<form enctype="multipart/form-data" action="txn-load-mac.php" method="post">
 <input type="hidden" name="type" value="<?=ashtml($type)?>">
 <input type="hidden" name="number" value="<?=ashtml($number)?>">
 <input type="file" name="src">
 <br>
 <input type="submit" name="Import from MacPhersons">
</form>
<?
  break;
}

