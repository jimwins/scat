<?
require '../scat.php';
require '../lib/txn.php';
?>
<html>
<head>
 <title></title>
 <link href="style.css" rel="stylesheet" type="text/css">
</head>
<?if ($_GET['print']) {?>
<body onload="window.print()">
<?}?>
<?
$id= (int)$_REQUEST['id'];
if (!$id) die("No transaction specified.");

date_default_timezone_set('America/Los_Angeles');

$details= txn_load($db, $id);
?>
<div id="store_name">Raw Materials</div>
<div id="doc_header">
 <div id="doc_info">
  <span id="doc_name">Invoice <?=ashtml($details['formatted_number'])?></span>
  <b>Created: <?=ashtml(date('F j, Y g:i A', strtotime($details['created'])))?></b><br>
<?if ($details['paid']) {?>
  <b>Paid: <?=ashtml(date('F j, Y g:i A', strtotime($details['paid'])))?></b>
<?}?>
 </div>
 <div id="store_info">
  <small>From:</small><br>
  <b>Raw Materials</b><br>
  436 South Main Street<br>
  Los Angeles, CA 90013<br>
  (213) 627-7223<br>
  info@rawmaterialsLA.com<br>
  http://rawmaterialsLA.com/
 </div>
 <div id="client_info">
<?
if ($details['person']) {
  $q= "SELECT * FROM person WHERE id = $details[person]";
  $r= $db->query($q)
    or die($db->error);
  $person= $r->fetch_assoc();

  echo '<small>To:</small><br>';
  echo '<b>',
       ashtml($person['company']),
       ($person['company'] && $person['name']) ? '<br>' : '',
       ashtml($person['name']),
       '</b><br>';
  if ($person['address']) {
    echo nl2br(ashtml($person['address'])), '<br>';
  }
  if ($person['phone']) {
    echo 'Phone: ', ashtml($person['phone']), '<br>';
  }
  if ($person['email']) {
    echo 'Email: ', ashtml($person['email']), '<br>';
  }
}
?>
 </div>
 <div style="clear:both;"></div>
</div>
<?
$q= "SELECT
            -1 * allocated AS Qty,
            IFNULL(override_name, item.name) Name,
            IF(txn_line.discount_type,
               CASE txn_line.discount_type
                 WHEN 'percentage' THEN
                   CONCAT('MSRP $', txn_line.retail_price, ' / ',
                          'Sale: ', ROUND(txn_line.discount, 0), '%')
                 WHEN 'relative' THEN
                   CONCAT('MSRP $', txn_line.retail_price, ' / ',
                          'Sale: $', txn_line.discount, ' off')
                 WHEN 'fixed' THEN
                   CONCAT('MSRP $', txn_line.retail_price)
               END,
               '') Description,
            IF(txn_line.discount_type,
               CASE txn_line.discount_type
                 WHEN 'percentage' THEN CAST(ROUND_TO_EVEN(txn_line.retail_price * ((100 - txn_line.discount) / 100), 2) AS DECIMAL(9,2))
                 WHEN 'relative' THEN (txn_line.retail_price - txn_line.discount) 
                 WHEN 'fixed' THEN (txn_line.discount)
               END,
               txn_line.retail_price) Price,
            IF(txn_line.discount_type,
               CASE txn_line.discount_type
                 WHEN 'percentage' THEN CAST(ROUND_TO_EVEN(txn_line.retail_price * ((100 - txn_line.discount) / 100), 2) AS DECIMAL(9,2))
                 WHEN 'relative' THEN (txn_line.retail_price - txn_line.discount) 
                 WHEN 'fixed' THEN (txn_line.discount)
               END,
               txn_line.retail_price) * -1 * allocated Total
       FROM txn
       LEFT JOIN txn_line ON (txn.id = txn_line.txn)
       JOIN item ON (txn_line.item = item.id)
      WHERE txn.id = $id
      ORDER BY line ASC";

$r= $db->query($q);
if (!$r) die($db->error);

?>
<table id="products" cellspacing="0" cellpadding="0">
 <thead>
  <tr><th class="right">#</th><th class="left">Name</th><th class="right">Price</th><th class="right">Total</th></tr>
 </thead>
 <tbody>
<?
while ($row= $r->fetch_assoc()) {
  echo '<tr valign="top">',
       '<td class="right">', $row['Qty'], '</td>',
       '<td class="left">', $row['Name'],
       ($row['Description'] ? ('<div class="description">' . $row['Description'] . '</div>') : ''),
       '</td>',
       '<td class="right">', amount($row['Price']), '</td>',
       '<td class="right">', amount($row['Total']), '</td>',
       "</tr>\n";
}
?>
  <tr class="sub">
   <td class="right" colspan="3">Subtotal:</td>
   <td class="price"><?=amount($details['subtotal'])?></td>
  </tr>
  <tr>
   <td class="right" colspan="3">Sales Tax (<?=$details['tax_rate']?>%):</td>
   <td class="price"><?=amount($details['total'] - $details['subtotal'])?></td>
  </tr>
  <tr class="total">
   <td class="right" colspan="3">Total:</td>
   <td class="price"><?=amount($details['total'])?></td>
  </tr>
<?
$q= "SELECT processed, method, discount, amount
       FROM payment
      WHERE txn = $id
      ORDER BY processed ASC";
$r= $db->query($q);

$methods= array(
  'cash' => 'Cash',
  'change' => 'Change',
  'credit' => 'Credit Card',
  'square' => 'Square',
  'dwolla' => 'Dwolla',
  'gift' => 'Gift Card',
  'check' => 'Check',
  'discount' => 'Discount',
  'bad' => 'Bad Debt',
);

if ($r->num_rows) {
  while ($payment= $r->fetch_assoc()) {
    if ($payment['method'] == 'discount' && $payment['discount']) {
      $method= sprintf("Discount (%d%%)", $payment['discount']);
    } else {
      $method= $methods[$payment['method']];
    }
    echo '<tr>',
         '<td class="right" colspan="3">',
         $method,
         ':</td>',
         '<td class="price">',
         amount($payment['amount']),
         "</td></tr>\n";
  }
?>
  <tr class="total">
   <td class="right" colspan="3">Total Due:</td>
   <td class="price"><?=amount($details['total'] - $details['total_paid'])?></td>
  </tr>
<?
}
?>
 </tbody>
</table>
<div id="store_footer">
Items purchased from stock may be returned in original condition and packaging
within 30 days with receipt. No returns without original receipt.
<br><br>
http://rawmaterialsLA.com/
</div>

