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
table {font-size:1em; width:100%; margin:2em 0;
        border-bottom:2px solid #000; border-left:0; border-right:0;}
th {padding:0.2em 0.1em; text-align: right; }
th:after { content: ":" }
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

if (!$id) die("no payment specified.");

$q= "SELECT payment.id, CAST(amount AS DECIMAL(9,2)) amount,
            cc_approval, cc_lastfour, cc_expire, cc_type, processed,
            CONCAT(DATE_FORMAT(txn.created, '%Y'), '-', number) AS invoice
       FROM payment
       JOIN txn ON (txn.id = txn)
      WHERE payment.id = $id";

$r= $db->query($q)
  or die($db->error);

$payment= $r->fetch_assoc();
?>
<table>
 <tr><th>Date</th><td><?=$payment['processed']?></td></tr>
 <tr><th>ID</th><td><?=$payment['id']?></td></tr>
 <tr><th>Card Type</th><td><?=$payment['cc_type']?></td></tr>
 <tr><th>Card Number</th><td><?=str_repeat('#', !strcmp($payment['cc_type'],'AmericanExpress') ? 11 : 12)?><?=$payment['cc_lastfour']?></td></tr>
 <tr><th>Expiration</th><td>##/##</td></tr>
 <tr><th>Approval</th><td><?=$payment['cc_approval']?></td></tr>
 <tr><th>Amount</th><td>$<?=$payment['amount']?></td></tr>
</table>

<div id="signature">
  <br>
  <div style="font-size: 2em; padding-top: 2em; padding-bottom: 0.25em; margin-bottom: 0.25em; border-bottom: 4px solid black; text-align: left; page-break-before: always;">&times;</div>
  Cardmember agrees to pay total in accordance with agreement governing use of such card.
</div>
<div id="doc_info">
  MERCHANT COPY
  <br>
  Invoice <?=ashtml($payment['invoice'])?>
  <br>
  <?=ashtml($payment['processed'])?>
</div>
<div id="store_footer">
Items purchased from stock may be returned in original condition and packaging
within 30 days with receipt. No returns without original receipt.
<br><br>
http://rawmaterialsLA.com/
</div>
