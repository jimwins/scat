<?
require 'scat.php';
require 'lib/catalog.php';

head("Brands @ Scat", true);

$q= "SELECT id, name, slug,
            (SELECT COUNT(*)
               FROM item
              WHERE brand = brand.id
                AND item.active AND NOT item.deleted) items
       FROM brand
   /* WHERE brand.active */
      ORDER BY 2";
$r= $db->query($q) or die_query($db, $q);

echo '<div style="column-count: 3" class="list-group">';
echo '<button type="button" id="add-brand" class="list-group-item">Add New Brand</button>';
while (($row= $r->fetch_assoc())) {
  echo '<div class="list-group-item" style="break-inside: avoid-column">',
       '<a onclick="editBrand(', $row['id'], ')">',
       '<i class="fa fa-edit"></i></a> ',
       ashtml($row['name']),
       '<a class="badge" href="items.php?search=brand:', ashtml($row['slug']), '">',
       $row['items'], '</a>',
       "</div>\n";
}
echo '</div>';
?>
<script>
$(function() {

$('#add-brand').on('click', function() {
  editBrand(0);
});

});

function editBrand(id) {
  Scat.dialog('brand').done(function (html) {
    var panel= $(html);

    var brand= { id: 0, name: '', slug: '' };
    brand.error= '';

    panel.on('hidden.bs.modal', function() {
      $(this).remove();
    });

    brandModel= ko.mapping.fromJS(brand);

    if (id) {
      Scat.api('brand-load', { id: id })
          .done(function (data) {
            ko.mapping.fromJS(data, brandModel);
          });
    }

    brandModel.saveBrand= function(place, ev) {
      var brand= ko.mapping.toJS(brandModel);
      delete brand.vendors;
      delete brand.error;

      Scat.api(brand.id ? 'brand-update' : 'brand-add', brand)
          .done(function (data) {
            $(place).closest('.modal').modal('hide');
            // XXX reload?
          });
    }

    ko.applyBindings(brandModel, panel[0]);
    panel.appendTo($('body')).modal();
  });
}

</script>
<?
foot();
?>
