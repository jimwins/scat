<?
require '../scat.php';
require '../lib/txn.php';
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
  font-size:1.5em;
  font-weight:bold;
  font-family: 'Directa Serif';
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

.cc-info {font-size:1em; width:100%; margin:2em 0;
        border-bottom:2px solid #000; border-left:0; border-right:0;}
.cc-info th { border: none; }
.cc-info th:after { content: ":" }

#doc_info {text-align:center;}
#signature {margin:2em 0; padding:5px 0px; text-align:center;}
#nosignature {margin:2em 0; text-align: center; padding: 5px 0px; }
#store_footer {margin:2em 0; padding:5px 0px; text-align:center;}

</style>
<div id="doc_header">
  <div id="store_name">Raw Materials Art Supplies</div>
  436 South Main Street<br>
  Los Angeles, CA 90013<br>
  (800) 729-7060<br>
  M-F 11-7, Sat 11-6, Sun 12-5<br>
  info@RawMaterialsLA.com<br>
  http://RawMaterialsLA.com/
</div>
<?

$id= (int)$_REQUEST['id'];

if (!$id) die("No transaction specified.");

$txn= txn_load($db, $id);

$items= txn_load_items($db, $id);
?>
<table id="products" cellspacing="0" cellpadding="0">
 <thead>
  <tr><th class="qty">QTY</th><th class="left">PRODUCT</th><th class="price">PRICE</th></tr>
 </thead>
 <tbody>
<?
foreach ($items as $item) {
  echo '<tr>',
       '<td class="qty">', $item['quantity'], '</td>',
       '<td class="left">', $item['name'],
       ($item['discount'] ? ('<div class="description">' . $item['discount'] . '</div>') : ''),
       '</td>',
       '<td class="price">', amount($item['ext_price']), '</td>',
       "</tr>\n";
}
?>
  <tr class="sub">
   <td class="right" colspan="2">Subtotal:</td>
   <td class="price"><?=amount($txn['subtotal'])?></td>
  </tr>
  <tr>
   <td class="right" colspan="2">Sales (<?=$txn['tax_rate']?>%):</td>
   <td class="price"><?=amount($txn['total'] - $txn['subtotal'])?></td>
  </tr>
  <tr class="total">
   <td class="right" colspan="2">Total:</td>
   <td class="price"><?=amount($txn['total'])?></td>
  </tr>
<?
$payments= txn_load_payments($db, $id);

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

if (count($payments)) {
  foreach ($payments as $payment) {
    if ($payment['method'] == 'discount' && $payment['discount']) {
      $method= sprintf("Discount (%d%%)", $payment['discount']);
    } else {
      $method= $methods[$payment['method']];
    }
    echo '<tr>',
         '<td class="right" colspan="2">',
         $method,
         ':</td>',
         '<td class="price">',
         amount($payment['amount']),
         "</td></tr>\n";
  }
?>
  <tr class="total">
   <td class="right" colspan="2">Total Due:</td>
   <td class="price"><?=amount($txn['total'] - $txn['total_paid'])?></td>
  </tr>
<?
}
?>
 </tbody>
</table>
<?
$credit= 0;
foreach ($payments as $payment) {
  if ($payment['method'] == 'credit') {
    $credit++;
?>
<table class="cc-info">
 <tr><th>Date</th><td><?=$payment['processed']?></td></tr>
 <tr><th>ID</th><td><?=$payment['id']?></td></tr>
 <tr><th>Card Type</th><td><?=$payment['cc_type']?></td></tr>
 <tr><th>Card Number</th><td><?=str_repeat('#', !strcmp($payment['cc_type'],'AmericanExpress') ? 11 : 12)?><?=$payment['cc_lastfour']?></td></tr>
 <tr><th>Expiration</th><td>##/##</td></tr>
<?if ($payment['cc_approval']) {?>
 <tr><th>Approval</th><td><?=$payment['cc_approval']?></td></tr>
<?}?>
 <tr><th>Amount</th><td><?=amount($payment['amount'])?></td></tr>
</table>
<?
  }
}
?>
<div id="doc_info">
<?if ($credit) {?>
  CUSTOMER COPY
  <br>
<?}?>
  Invoice <?=ashtml($txn['formatted_number'])?>
  <br>
  <?=date('F j, Y g:i A', strtotime($txn['created']))?>
  <br><br>
<span style="font-family: Aatrix3of9Reg; font-size: 2em">*@INV-<?=ashtml($txn['id'])?>*</span>
</div>
<div id="store_footer">
Items purchased from stock may be returned in original condition and packaging
within 30 days with receipt. Assembled easels are subject to a 20% restocking fee. No returns without original receipt.
<br><br>
http://RawMaterialsLA.com/
</div>
