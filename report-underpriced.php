<?
require 'scat.php';
require 'lib/item.php';

$minimum= (float)$_REQUEST['minimum'];
if (!$minimum) $minimum= 0.3;

$q= "SELECT id, code, name,
            retail_price,
            sale_price(retail_price, discount_type, discount) sale_price,
            (SELECT MIN(IF(promo_price, IF(promo_price < net_price,
                                           promo_price, net_price),
                           net_price))
               FROM vendor_item
              WHERE vendor_item.item = item.id AND vendor_item.active) net_price
       FROM item
      WHERE active AND NOT deleted
    HAVING (sale_price - net_price) / sale_price < $minimum
     ORDER BY 2";

$r= $db->query($q)
  or die_query($db, $q);

head("Underpriced @ Scat", true);
?>
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
