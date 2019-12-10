<?
require 'scat.php';
require 'lib/item.php';

$sql_criteria= "1=1";
if (($items= $_REQUEST['items'])) {
  list($sql_criteria, $x)= item_terms_to_sql($db, $_REQUEST['items'], 0);
}

$minimum= (float)$_REQUEST['minimum'];
if (!$minimum) $minimum= 0.3;

$q= "SELECT item.id, item.code, item.name,
            retail_price,
            sale_price(retail_price, discount_type, discount) sale_price,
            (SELECT MIN(IF(promo_price, IF(promo_price < net_price,
                                           promo_price, net_price),
                           net_price))
               FROM vendor_item
              WHERE vendor_item.item = item.id AND vendor_item.active) net_price
       FROM item
       LEFT JOIN brand ON item.brand = brand.id
      WHERE item.active AND NOT item.deleted
        AND ($sql_criteria)
    HAVING (sale_price - net_price) / sale_price < $minimum
     ORDER BY 2";

$r= $db->query($q)
  or die_query($db, $q);

head("Underpriced @ Scat", true);
?>
<form id="report-params" class="form-horizontal" role="form"
      action="<?=$_SERVER['PHP_SELF']?>">
  <div class="form-group">
    <label for="items" class="col-sm-2 control-label">
      Items
    </label>
    <div class="col-sm-10">
      <input id="items" name="items" type="text"
             class="form-control" style="width: 20em"
             value="<?=ashtml($items)?>">
    </div>
  </div>
  <div class="form-group">
    <label for="items" class="col-sm-2 control-label">
      Minimum Margin
    </label>
    <div class="col-sm-10">
      <input id="minimum" name="minimum" type="text"
             class="form-control" style="width: 20em"
             value="<?=ashtml($minimum)?>">
    </div>
  </div>
  <div class="form-group">
    <div class="col-sm-offset-2 col-sm-10">
      <input type="submit" class="btn btn-primary" value="Show">
    </div>
  </div>
</form>
<div id="results">
  <table class="table table-striped table-hover sortable">
    <thead>
      <tr>
        <th class="num">#</th>
        <th>Code</th>
        <th>Name</th>
        <th>Net</th>
        <th>Sale</th>
        <th>Margin</th>
      </tr>
    </thead>
    <tbody>
<?while ($row= $r->fetch_assoc()) {?>
      <tr>
        <td class="num"><?=++$id?></td>
        <td>
          <a href="item.php?id=<?=$row['id']?>"><?=ashtml($row['code'])?></a>
        </td>
        <td>
          <?=ashtml($row['name'])?>
        </td>
        <td>
          <?=amount($row['net_price'])?>
        </td>
        <td>
          <?=amount($row['sale_price'])?>
        </td>
        <td>
          <?=sprintf('%.2f%%',
                     ($row['sale_price'] - $row['net_price'])
                     / $row['sale_price'] * 100)?>
        </td>
      </tr>
<?}?>
    </tbody>
  </table>
</div>
<?
foot();
?>
