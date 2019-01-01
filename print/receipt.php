<?
require '../scat.php';
require '../lib/txn.php';

use Smalot\Cups\Builder\Builder;
use Smalot\Cups\Manager\JobManager;
use Smalot\Cups\Manager\PrinterManager;
use Smalot\Cups\Model\Job;
use Smalot\Cups\Transport\Client;
use Smalot\Cups\Transport\ResponseParser;

ob_start();
?>
<style type="text/css">
body {
  font-family: Monaco, monospace;
  font-size: 28px;
  text-align: left;
  color: #000;
}

.right {
  text-align: right;
}
.left {
  text-align: left;
}

#doc_header {
  padding-top: 1em;
  margin-bottom: 2em;
  padding-bottom:1em;
  border-bottom:2px solid #000;
  text-align:center;
}
table#products {font-size:1em; width:100%; margin:2em 0;
        border-top:2px solid #000; border-bottom:2px solid #000; border-left:0; border-right:0;}
th {padding:0.2em 0.1em; border-bottom:1px solid #000;}
.qty {padding:0.2em 0.5em; text-align:right;} /* tr's and th's */
.price {padding:0.2em 0.1em; white-space:nowrap; text-align:right;}
.description { font-size: 0.75em; }
td {padding:0.2em 0.1em; vertical-align:top;}
tr.sub td {border-top:2px solid #000; border-bottom:2px solid #000;}
tr.total td {border-top:6px solid #000; text-align:right; }

.cc-info {font-size:1em; width:100%; margin:2em 0;
        border-bottom:2px solid #000; border-left:0; border-right:0;}
.cc-info th { border: none; }
.cc-info th:after { content: ":" }

#loyalty {
  text-align: center;
  margin: 1em 2em;
  padding: 1em;
  border: 2px solid black;
}

#doc_info {text-align:center;}
#signature {margin:2em 0; padding:5px 0px; text-align:center;}
#nosignature {margin:2em 0; text-align: center; padding: 5px 0px; }
#store_footer {margin:2em 0; padding:5px 0px; text-align:center;}

</style>
<div id="doc_header">
  <div id="store_name">
  <img src="data:<?=mime_content_type('../ui/logo.png')?>;base64,<?=base64_encode(file_get_contents('../ui/logo.png'))?>" width="80%">
  </div>
  436 South Main Street<br>
  Los Angeles, CA 90013<br>
  (800) 729-7060<br>
  M-F 10-7, Sat 11-6, Sun 12-5<br>
  info@RawMaterialsLA.com<br>
  https://RawMaterialsLA.com/
</div>
<?

$id= (int)$_REQUEST['id'];

if (!$id) die("No transaction specified.");

$gift= (int)$_REQUEST['gift'];

$txn= txn_load($db, $id);
$items= txn_load_items($db, $id);
$person= person_load($db, $txn['person']);

function pts($num) {
  return sprintf("%d point%s", $num, $num > 1 ? 's' : '');
}
?>
<table id="products" cellspacing="0" cellpadding="0">
  <tr>
    <th class="qty">QTY</th>
    <th class="left">PRODUCT</th>
<?if (!$gift) {?>
    <th class="price">PRICE</th>
<?}?>
  </tr>
<?
foreach ($items as $item) {
  echo '<tr>',
       '<td class="qty">', $item['quantity'], '</td>',
       '<td class="left">', $item['name'],
       ($item['discount'] ? ('<div class="description">' . $item['discount'] . '</div>') : ''),
       '</td>';
  if (!$gift) {
    echo '<td class="price">', amount($item['ext_price']), '</td>';
  }
  echo "</tr>\n";
}
if (!$gift) {
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
  'stripe' => 'Stripe',
  'dwolla' => 'Dwolla',
  'paypal' => 'PayPal',
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
</table>
<?
$credit= $used_cash= 0;
foreach ($payments as $payment) {
  if ($payment['method'] == 'cash' || $payment['method'] == 'change') {
    $used_cash++;
  }
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
<?if ($payment['cc_txn']) {?>
 <tr><th>Ref #</th><td><?=$payment['cc_txn']?></td></tr>
<?}?>
 <tr><th>Amount</th><td><?=amount($payment['amount'])?></td></tr>
<?
  }
}
}
?>
</table>

<?if (!$gift && !$person['suppress_loyalty']) {?>
<div id="loyalty">
<?if ($person['id']) {
    $points= (int)$txn['subtotal'];
    if ($points == 0 && $txn['subtotal'] > 0) $points= 1;
  ?>
  You earned <?=pts($points)?> with this purchase.
  <br><br>
  That means you have <?=pts($person['points_pending'] + $person['points_available'])?> to redeem towards rewards tomorrow!
<?} else {?>
  Earn store credit by signing up for our rewards program!
  <br><br>
  https://rawm.us/rewards
  <br><br>
  Code: <?=sprintf("%08X %08X", strtotime($txn['created']), $txn['id'])?>
<?}?>
</div>
<?}?>

<div id="doc_info">
<?if ($credit) {?>
  CUSTOMER COPY
  <br>
<?}?>
<?if ($gift) {?>
  GIFT RECEIPT
  <br>
<?}?>
  Invoice <?=ashtml($txn['formatted_number'])?>
  <br>
<?if ($txn['paid']) {?>
  Created: 
<?}?>
  <?=date('F j, Y g:i A', strtotime($txn['created']))?>
<?if ($txn['paid']) {?>
  <br>
  Paid: <?=date('F j, Y g:i A', strtotime($txn['paid']))?>
<?}?>
  <br><br>
<?if (defined('PRINT_DIRECT')) {?>
  <barcode code="@INV-<?=ashtml($txn['id'])?>" type="C39E" class="barcode" size="2" />
<?} else {?>
  <span style="font-family: Aatrix3of9Reg; font-size: 2em">*@INV-<?=ashtml($txn['id'])?>*</span>
<?}?>
</div>
<div id="store_footer">
Items purchased from stock may be returned in original condition and packaging
within 30 days with receipt. Assembled easels are subject to a 20% restocking fee. No returns without original receipt.
<br><br>
http://RawMaterialsLA.com/
</div>
<?
$html= ob_get_clean();

if (defined('PRINT_DIRECT')) {
  define('_MPDF_TTFONTDATAPATH', '/tmp/ttfontdata');
  @mkdir(_MPDF_TTFONTDATAPATH);

  #$mpdf= new \Mpdf\Mpdf('utf8','letter',28,'',15,15,9,10,'P');
  $mpdf= new \Mpdf\Mpdf([ 'mode' => 'utf-8', 'format' => 'letter',
                          'tempDir' => '/tmp',
                          'margin_left' => 15, 'margin_right' => 15,
                          'margin_top' => 9, 'margin_bottom' => 10,
                          'default_font_size' => 28  ]);
  $mpdf->writeHTML($html);

  $tmpfname= tempnam("/tmp", "rec");

  if ($_REQUEST['DEBUG']) {
    $mpdf->Output();
    exit;
  }

  $mpdf->Output($tmpfname, 'f');

  if (!defined('CUPS_HOST')) {
    $printer= RECEIPT_PRINTER;
    $option= "";
    if ($used_cash) {
      $option= "-o CashDrawerSetting=1OpenDrawer1";
    }
    shell_exec("lpr -P$printer $option $tmpfname");
  } else {
    $client= new Client(CUPS_USER, CUPS_PASS,
                        [ 'remote_socket' => 'tcp://' . CUPS_HOST ]);
    $builder= new Builder(null, true);
    $responseParser= new ResponseParser();

    $printerManager= new PrinterManager($builder, $client, $responseParser);
    $printer= $printerManager->findByUri('ipp://' . CUPS_HOST .
                                         '/printers/' . RECEIPT_PRINTER);

    $jobManager= new JobManager($builder, $client, $responseParser);

    $job= new Job();
    $job->setName('job create file');
    $job->setCopies(1);
    $job->setPageRanges('1-1000');
    $job->addFile($tmpfname);
    if ($used_cash) {
      $job->addAttribute('CashDrawerSetting', '1OpenDrawer1');
    }
    $result= $jobManager->send($printer, $job);
  }

  echo jsonp(array("result" => "Printed."));
} else {
  echo $html;
}
