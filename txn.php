<?
require 'scat.php';

head("transaction");

$id= (int)$_REQUEST['id'];

$type= $_REQUEST['type'];
$number= (int)$_REQUEST['number'];

if (!$id && $type) {
  $q= "SELECT id FROM txn
        WHERE type = '". $db->real_escape_string($type) ."'
          AND number = $number";
  $r= $db->query($q);

  if (!$r->num_rows)
      die("<h2>No such transaction found.</h2>");

  $row= $r->fetch_row();
  $id= $row[0];
}

if (!$id) die("no transaction specified.");

$q= "SELECT meta, Number\$txn, Created\$date, Person\$person,
            Ordered, Allocated,
            taxed Taxed\$dollar,
            untaxed Untaxed\$dollar,
            CAST(tax_rate AS DECIMAL(9,2)) Tax\$percent,
            CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                 AS DECIMAL(9,2))
            Total\$dollar,
            Paid\$dollar
      FROM (SELECT
            txn.type AS meta,
            CONCAT(txn.id, '|', type, '|', txn.number) AS Number\$txn,
            txn.created AS Created\$date,
              CONCAT(txn.person, '|', IFNULL(person.company,''),
                     '|', IFNULL(person.name,''))
                AS Person\$person,
            SUM(ordered) AS Ordered,
            SUM(allocated) AS Allocated,
            CAST(ROUND_TO_EVEN(
              SUM(IF(txn_line.taxfree, 1, 0) *
                IF(type = 'customer', -1, 1) * allocated *
                CASE discount_type
                  WHEN 'percentage' THEN
                    ROUND_TO_EVEN(retail_price * ((100 - discount) / 100), 2)
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
                 AS DECIMAL(9,2)) AS Paid\$dollar
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
       LEFT JOIN person ON (txn.person = person.id)
      WHERE txn.id = $id) t";

$r= $db->query($q)
  or die($db->error);

$txn= $r->fetch_assoc();

$r->data_seek(0);
dump_table($r);
dump_query($q);

?>
<button id="receipt">Print Receipt</button>
<script>
$("#receipt").live('click', function() {
  var lpr= $('<iframe id="receipt" src="receipt.php?print=1&amp;id=<?=$id?>"></iframe>').hide();
  $(this).children("#receipt").remove();
  $(this).append(lpr);
});
</script>
<?

$type= $db->real_escape_string($type);

$q= "SELECT
            line AS `#\$num`,
            item.code Code\$item,
            IFNULL(override_name, item.name) Name,
            txn_line.retail_price Price\$dollar,
            IF(txn_line.discount_type,
               CASE txn_line.discount_type
                 WHEN 'percentage' THEN CAST(ROUND_TO_EVEN(txn_line.retail_price * ((100 - txn_line.discount) / 100), 2) AS DECIMAL(9,2))
                 WHEN 'relative' THEN (txn_line.retail_price - txn_line.discount) 
                 WHEN 'fixed' THEN (txn_line.discount)
               END,
               NULL) Sale\$dollar,
            CASE txn_line.discount_type
              WHEN 'percentage' THEN CONCAT(ROUND(txn_line.discount), '% off')
              WHEN 'relative' THEN CONCAT('$', txn_line.discount, ' off')
            END Discount,
            ordered as Ordered,
            shipped as Shipped,
            allocated as Allocated
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
       JOIN item ON (txn_line.item = item.id)
      WHERE txn.id = $id
      ORDER BY line ASC";

dump_table($db->query($q));
dump_query($q);

if (preg_match('/customer/', $txn['Number$txn'])) {
  echo '<h2>Payments</h2>';
  $q= "SELECT processed AS Date,
              method AS Method\$payment,
              amount AS Amount\$dollar
         FROM payment
        WHERE txn = $id
        ORDER BY processed ASC";
  dump_table($db->query($q));
  dump_query($q);
}
