<?
require '../scat.php';
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
table {font-size:larger; width:100%; padding:2em 0;
        border-bottom:2px solid #000; border-left:0; border-right:0;}
th {padding:0.2em 0.1em; text-align: right; }
th:after { content: ":" }
.qty {padding:0.2em 0.5em; text-align:right;} /* tr's and th's */
.price {padding:0.2em 0.1em; white-space:nowrap; text-align:right;}
.description { font-size: 0.75em; }
td {padding:0.2em 0.1em; vertical-align:top;}
tr.sub td {border-top:2px solid #000; border-bottom:2px solid #000;}
tr.total td, tr.total th {border-top:solid #000 6px; }

#doc_info {
  text-align: center;
  font-size: 1.5em;
  padding-top: 1em;
}
#signature {margin:2em 0; padding:5px 0px; text-align:center;}
#nosignature {margin:2em 0; text-align: center; padding: 5px 0px; }
#store_footer {margin:2em 0; padding:5px 0px; text-align:center;}

</style>
<div id="doc_header">
  <div id="store_name">Raw Materials Art Supplies</div>
  436 South Main Street<br>
  Los Angeles, CA 90013<br>
  (800) 729-7060<br>
  info@RawMaterialsLA.com<br>
  http://RawMaterialsLA.com/
</div>
<div style="font-size: 1.5em; text-align: center">DEPOSIT</div>
<?

$id= (int)$_REQUEST['id'];

if (!$id) die("No transaction specified.");

$q= "SELECT amount FROM payment
      WHERE txn = $id AND method = 'withdrawal'";

$r= $db->query($q)
  or die($db->error);

$cash= $r->fetch_assoc();

$q= "SELECT created FROM txn
      WHERE type = 'drawer' AND id < $id
      ORDER BY id DESC
      LIMIT 1";
$last_txn= $db->get_one($q);

$q= "SELECT processed, amount
       FROM payment
      WHERE method = 'check' AND processed > '$last_txn'";
$r= $db->query($q)
  or die($db->error);

$total= abs($cash['amount']);
?>
<table>
 <tr><th>Cash</th><td><?=amount(abs($cash['amount']));?></td></tr>
<?
while ($check= $r->fetch_assoc()) {
  $total+= $check['amount'];
?>
 <tr><th>Check</th><td><?=amount($check['amount'])?></td></tr>
<?}?>
 <tr class="total"><th>Total</th><td><?=amount($total);?></td></tr>
</table>

<div id="doc_info">
<?=BANK_DEPOSIT_INFO?>
</div>
</body>
</html>
