<?
require '../scat.php';
require '../lib/txn.php';

$id= (int)$_REQUEST['id'];
if (!$id) die("No transaction specified.");

date_default_timezone_set('America/Los_Angeles');

$details= txn_load($db, $id);
?>
<html>
<head>
 <title><?='I'.ashtml($details['formatted_number'])?></title>
 <link href="style.css" rel="stylesheet" type="text/css">
</head>
<?if ($_GET['print']) {?>
<body onload="window.print()">
<?}?>
<div id="store_name">Raw Materials Art Supplies</div>
<div id="doc_header">
 <div id="doc_info">
  <span id="doc_name"><?=($details['type'] == 'vendor') ? 'PO' : 'Invoice'?> <?=ashtml($details['formatted_number'])?></span>
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
  (800) 729-7060<br>
  info@RawMaterialsLA.com<br>
  http://RawMaterialsLA.com/
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
$items= txn_load_items($db, $id);
?>
<table id="products" cellspacing="0" cellpadding="0">
 <thead>
  <tr>
    <th class="right">#</th>
    <?=($details['type'] == 'vendor' ? '<th class="left">Code</th>' : '')?>
    <th class="left">Name</th>
    <th class="right">Price</th>
    <th class="right">Total</th>
  </tr>
 </thead>
 <tbody>
<?
foreach ($items as $item) {
  echo '<tr valign="top">',
       '<td class="right">', $item['quantity'], '</td>',
       ($details['type'] == 'vendor' ? '<td class="left">' . $item['code'] . '</td>' : ''),
       '<td class="left">', $item['name'],
       ($item['discount'] ? ('<div class="description">' . $item['discount'] . '</div>') : ''),
       '</td>',
       '<td class="right">', amount($item['price']), '</td>',
       '<td class="right">', amount($item['ext_price']), '</td>',
       "</tr>\n";
}
$span= ($details['type'] == 'vendor') ? 4 : 3;
?>
  <tr class="sub">
   <td class="right" colspan="<?=$span?>">Subtotal:</td>
   <td class="price"><?=amount($details['subtotal'])?></td>
  </tr>
  <tr>
   <td class="right" colspan="<?=$span?>">Sales Tax (<?=$details['tax_rate']?>%):</td>
   <td class="price"><?=amount($details['total'] - $details['subtotal'])?></td>
  </tr>
  <tr class="total">
   <td class="right" colspan="<?=$span?>">Total:</td>
   <td class="price"><?=amount($details['total'])?></td>
  </tr>
<?
$methods= array(
  'cash' => 'Cash',
  'change' => 'Change',
  'credit' => 'Credit Card',
  'square' => 'Square',
  'stripe' => 'Stripe',
  'dwolla' => 'Dwolla',
  'gift' => 'Gift Card',
  'check' => 'Check',
  'discount' => 'Discount',
  'bad' => 'Bad Debt',
);

$payments= txn_load_payments($db, $id);

if (count($payments)) {
  foreach ($payments as $payment) {
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
<?if ($details['type'] != 'vendor') {?>
Items purchased from stock may be returned in original condition and packaging
within 30 days with receipt. No returns without original receipt.
<br><br>
<?}?>
http://RawMaterialsLA.com/
</div>

