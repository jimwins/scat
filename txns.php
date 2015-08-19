<?
require 'scat.php';

head("Transactions @ Scat", true);

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

if ($_REQUEST['total']) {
  $criteria[]= "CAST((SELECT SUM(amount) FROM payment WHERE txn.id = payment.txn)
                     AS DECIMAL(9,2)) = '" . $db->escape($_REQUEST['total']) . "'";
}

if (empty($criteria)) {
  $criteria= '1=1';
} else {
  $criteria= join(' AND ', $criteria);
}

$page= (int)$_REQUEST['page'];

?>
<form class="form-inline" method="get" action="txns.php">
  <input type="submit" class="btn btn-primary" value="Show">
  <select name="type" class="form-control">
   <option value="">Any
   <option value="customer" <?=($type=="customer")?' selected': ''?>>Invoice
   <option value="vendor" <?=($type=="vendor")?' selected': ''?>>Purchase Order
   <option value="correction" <?=($type=="correction")?' selected': ''?>>Correction
   <option value="drawer" <?=($type=="drawer")?' selected': ''?>>Till Count
  </select>
  that includes
  <input type="text" name="q" value="<?=ashtml($q)?>">
  <div class="checkbox">
    <label>
      <input type="checkbox" name="unfilled" value="1" <?=($_REQUEST['unfilled'])?' checked':''?>>
      Unfilled
    </label>
  </div>
  <div class="checkbox">
    <label>
      <input type="checkbox" name="unpaid" value="1" <?=($_REQUEST['unpaid'])?' checked':''?>>
      Unpaid
    </label>
  </div>
</form>
<br>
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
                sale_price(retail_price, discount_type, discount)),
              2) AS DECIMAL(9,2))
            untaxed,
            CAST(ROUND_TO_EVEN(
              SUM(IF(txn_line.taxfree, 0, 1) *
                IF(type = 'customer', -1, 1) * allocated *
                sale_price(retail_price, discount_type, discount)),
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
      ORDER BY txn.id DESC
      LIMIT $start, $per_page) t";

$r= $db->query($q);

$params= http_build_query($_GET);

ob_start();
?>
<nav>
  <ul class="pager">
    <li class="previous <?=($page == 0) ? 'disabled' : ''?>">
      <a href="txns.php?page=<?=($page - 1) . '&amp;' . ashtml($params)?>"><span aria-hidden="true">&larr;</span> Newer</a>
    </li>

    <li class="next">
      <a href="txns.php?page=<?=($page + 1) . '&amp;' . ashtml($params)?>">Older <span aria-hidden="true">&rarr;</span></a>
    </li>
  </ul>
</li>
<?
$nav= ob_get_contents();
ob_end_flush();

dump_table($db->query($q));

echo $nav;

dump_query($q);

foot();
