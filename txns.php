<?
require 'scat.php';

head("transactions");

$criteria= array();

$type= $_REQUEST['type'];
if ($type) {
  $criteria[]= "(type = '".$db->real_escape_string($type)."')";
}

$q= $_REQUEST['q'];
if ($q) {
  $criteria[]= "(person.name LIKE '%$q%'
             OR person.company LIKE '%$q%')";
}
if ($_REQUEST['unfilled']) {
  $criteria[]= "txn.filled IS NULL";
}
if ($_REQUEST['unpaid']) {
  $criteria[]= "txn.paid IS NULL";
}
if ($_REQUEST['untaxed']) {
  $criteria[]= "txn.tax_rate = 0";
}

if (empty($criteria)) {
  $criteria= '1=1';
} else {
  $criteria= join(' AND ', $criteria);
}

$page= (int)$_REQUEST['page'];

?>
<form method="get" action="txns.php">
<input type="submit" value="Show">
<select name="type">
 <option value="">Any
 <option value="customer">Invoice
 <option value="vendor">Purchase Order
 <option value="correction">Correction
 <option value="drawer">Till Count
</select>
that includes
<input type="text" name="q" value="<?=ashtml($q)?>">
<label><input type="checkbox" name="unfilled" value="1"> Unfilled</label>
<label><input type="checkbox" name="unpaid" value="1"> Unpaid</label>
</form>
<br>
<button id="new-po">New Purchase Order</button>
<form id="create-po" style="display: none">
<input type="text" id="person" size="40">
<br>
<input type="submit" value="Create">
</form>
<script>
$('#new-po').on('click', function(ev) {
  $('#create-po').modal({ persist: true });
});
$('#create-po #person').autocomplete({
  source: "./api/person-list.php?callback=?",
  minLength: 2,
  select: function(ev, ui) {
    $(this).data('id', ui.item.id);
  },
});
$('#create-po').on('submit', function(ev) {
  ev.preventDefault(true);

  var person= $('#person', this).data('id');

  $.modal.close();

  $.getJSON("api/txn-create.php?callback=?",
            { type: 'vendor', person: person },
            function (data) {
              if (data.error) {
                $.modal(data.error);
              }
              window.location= 'txn.php?id=' + data.txn.id;
            });
});
</script>
<?
$per_page= 50;
$start= $page * $per_page;

$q= "SELECT meta, Number\$txn,
            Created\$date, Filled\$date,
            Person\$person,
            Ordered, Allocated,
            CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                 AS DECIMAL(9,2))
            Total\$dollar,
            Paid\$dollar, Paid\$date
      FROM (SELECT
            txn.type AS meta,
            CONCAT(txn.id, '|', type, '|', txn.number) AS Number\$txn,
            txn.created AS Created\$date,
            txn.filled AS Filled\$date,
            CONCAT(txn.person, '|', IFNULL(person.company,''),
                   '|', IFNULL(person.name,''))
              AS Person\$person,
            SUM(ordered) * IF(txn.type = 'customer', -1, 1) AS Ordered,
            SUM(allocated) * IF(txn.type = 'customer', -1, 1) AS Allocated,
            CAST(ROUND_TO_EVEN(
              SUM(IF(txn_line.taxfree, 1, 0) *
                IF(type = 'customer', -1, 1) * allocated *
                CASE discount_type
                  WHEN 'percentage' THEN retail_price * ((100 - discount) / 100)
                  WHEN 'relative' THEN (retail_price - discount) 
                  WHEN 'fixed' THEN (discount)
                  ELSE retail_price
                END),
              2) AS DECIMAL(9,2))
            untaxed,
            CAST(ROUND_TO_EVEN(
              SUM(IF(txn_line.taxfree, 0, 1) *
                IF(type = 'customer', -1, 1) * allocated *
                CASE discount_type
                  WHEN 'percentage' THEN retail_price * ((100 - discount) / 100)
                  WHEN 'relative' THEN (retail_price - discount) 
                  WHEN 'fixed' THEN (discount)
                  ELSE retail_price
                END),
              2) AS DECIMAL(9,2))
            taxed,
            tax_rate,
            CAST((SELECT SUM(amount) FROM payment WHERE txn.id = payment.txn)
                 AS DECIMAL(9,2)) AS Paid\$dollar,
            txn.paid AS Paid\$date
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
       LEFT JOIN person ON (txn.person = person.id)
      WHERE $criteria
      GROUP BY txn.id
      ORDER BY created DESC
      LIMIT $start, $per_page) t";

dump_table($db->query($q));

# XXX preserve options
if ($page) {
  echo '<a href="txns.php?page=', $page - 1, '">&laquo; Previous</a>';
}
echo ' &mdash; <a href="txns.php?page=', $page + 1, '">Next &raquo;</a>';

echo '<br>';
dump_query($q);

foot();
