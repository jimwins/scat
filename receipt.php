<?
require 'scat.php';
?>
<?if ($_GET['print']) {?>
<body onload="window.print()">
<?}?>
<style type="text/css">
body {
  font:28px Monaco, monospace;
  text-align:left;
  color:#000;
  margin:0;
  padding:0;
}

header, footer {
  display: none;
}

.right {
  text-align: right;
}
.left {
  text-align: left;
}

#doc_header {
  margin-bottom: 2em;
  padding-bottom:1em;
  border-bottom:2px solid #000;
  text-align:center;
}
#store_name {
  font-size:2.5em;
  font-weight:bold;
  font-family: impact;
  text-transform: lowercase;
}
table#products {font-size:1em; width:100%; margin:2em 0;
        border-top:2px solid #000; border-bottom:2px solid #000; border-left:0; border-right:0;}
th {padding:0.2em 0.1em; border-bottom:1px solid #000;}
.qty {padding:0.2em 0.5em; text-align:right;} /* tr's and th's */
.price {padding:0.2em 0.1em; white-space:nowrap; text-align:right;}
.description { font-size: 0.75em; }
td {padding:0.2em 0.1em; vertical-align:top;}
tr.sub td {border-top:2px solid #000; border-bottom:2px solid #000;}
tr.total td {border-top:solid #000 6px; text-align:right; font-weight:;}

#doc_info {text-align:center;}
#signature {margin:2em 0; padding:5px 0px; text-align:center;}
#nosignature {margin:2em 0; text-align: center; padding: 5px 0px; }
#store_footer {margin:2em 0; padding:5px 0px; text-align:center;}

</style>
<div id="doc_header">
  <div id="store_name">Raw Materials</div>
  436 South Main Street<br>
  Los Angeles, CA 90013<br>
  (213) 627-7223<br>
  info@rawmaterialsLA.com<br>
  http://rawmaterialsLA.com/
</div>
<?

$id= (int)$_REQUEST['id'];

if (!$id) die("no transaction specified.");

$q= "SELECT meta, Number\$txn,
            DATE_FORMAT(Created\$date, '%b %e, %Y %l:%i %PM') Created,
            CONCAT(DATE_FORMAT(Created\$date, '%Y-'), number) FormattedNumber,
            Person\$person,
            Ordered, Shipped, Allocated,
            (taxed + untaxed) Subtotal,
            CAST(tax_rate AS DECIMAL(9,2)) tax_rate,
            CAST(ROUND_TO_EVEN(taxed * (tax_rate / 100), 2)
                 AS DECIMAL(9,2)) Tax,
            CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                 AS DECIMAL(9,2))
            Total,
            Paid\$dollar
      FROM (SELECT
            txn.type AS meta,
            txn.number,
            CONCAT(txn.id, '|', type, '|', txn.number) AS Number\$txn,
            txn.created AS Created\$date,
            CONCAT(txn.person, '|', IFNULL(person.company,''),
                   '|', IFNULL(person.name,''))
              AS Person\$person,
            SUM(ordered) AS Ordered,
            SUM(shipped) AS Shipped,
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

$r= $db->query($q);
$details= $r->fetch_assoc();

$q= "SELECT
            ABS(allocated) AS Qty,
            IFNULL(override_name, item.name) Name,
            IF(txn_line.discount_type,
               CASE txn_line.discount_type
                 WHEN 'percentage' THEN CAST(ROUND_TO_EVEN(txn_line.retail_price * ((100 - txn_line.discount) / 100), 2) AS DECIMAL(9,2))
                 WHEN 'relative' THEN (txn_line.retail_price - txn_line.discount) 
                 WHEN 'fixed' THEN (txn_line.discount)
               END,
               txn_line.retail_price) * ABS(allocated) Price
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
       JOIN item ON (txn_line.item = item.id)
      WHERE txn.id = $id
      ORDER BY line ASC";

$r= $db->query($q);

?>
<table id="products" cellspacing="0" cellpadding="0">
 <thead>
  <tr><th class="qty">QTY</th><th class="left">PRODUCT</th><th class="price">PRICE</th></tr>
 </thead>
 <tbody>
<?
while ($row= $r->fetch_assoc()) {
  echo '<tr>',
       '<td class="qty">', $row['Qty'], '</td>',
       '<td class="left">', $row['Name'], '</td>',
       '<td class="price">', amount($row['Price']), '</td>',
       "</tr>\n";
}
?>
  <tr class="sub">
   <td class="right" colspan="2">Subtotal:</td>
   <td class="price"><?=amount($details['Subtotal'])?></td>
  </tr>
  <tr>
   <td class="right" colspan="2">Sales (<?=$details['tax_rate']?>%):</td>
   <td class="price"><?=amount($details['Tax'])?></td>
  </tr>
  <tr class="total">
   <td class="right" colspan="2">Total:</td>
   <td class="price"><?=amount($details['Total'])?></td>
  </tr>
 </tbody>
</table>
<?

if ($type == 'customer') {
  echo '<h2>Payments</h2>';
  $q= "SELECT processed AS Date,
              method AS Method,
              amount AS Amount\$dollar
         FROM payment
        WHERE txn = $id
        ORDER BY processed ASC";
  dump_table($db->query($q));
  dump_query($q);
}
?>
<div id="doc_info">
  Invoice <?=ashtml($details['FormattedNumber'])?>
  <br>
  <?=ashtml($details['Created'])?>
</div>
<div id="store_footer">
Items purchased from stock may be returned in original condition and packaging
within 30 days with receipt. No returns without original receipt.
<br><br>
http://rawmaterialsLA.com/
</div>
