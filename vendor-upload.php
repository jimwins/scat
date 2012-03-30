<?
include 'scat.php';

head("vendor upload");
?>
<form id="upload-form" method="post" enctype="multipart/form-data" action="api/vendor-upload.php">
 <input name="vendor" type="hidden" value="">
 <input id="vendor" type="text" value="">
 <br>
 <input type="file" name="src">
 <br>
 <button>Load</button>
</form>
<script>
$('#upload-form #vendor').autocomplete({
  source: "./api/person-list.php?callback=?",
  minLength: 2,
  select: function(ev, ui) {
    $("#upload-form input[name='vendor']").val(ui.item.id);
  },
});
</script>
