<?
include 'scat.php';

head("Vendor Upload @ Scat");
?>
<style>
.btn-file {
  position: relative;
  overflow: hidden;
}
.btn-file input[type=file] {
  position: absolute;
  top: 0;
  right: 0;
  min-width: 100%;
  min-height: 100%;
  font-size: 999px;
  text-align: right;
  filter: alpha(opacity=0);
  opacity: 0;
  outline: none;
  background: white;
  cursor: inherit;
  display: block;
}
</style>
<form id="upload-form" method="post"
      enctype="multipart/form-data"
      action="api/vendor-upload.php">
  <input name="vendor" type="hidden" value="">
  <div class="form-group">
    <label for="vendor">Vendor</label>
    <input id="vendor" type="text" class="form-control">
  </div>
  <div class="form-group">
    <div class="input-group">
      <span class="input-group-btn">
        <button class="btn btn-default btn-file">
          Browse <input type="file" name="src">
        </button>
      </span>
      <input type="text" class="form-control" placeholder="Filename" readonly>
    </div>
  </div>
  <button class="btn btn-primary">Upload</button>
</form>
<script>
$('#upload-form #vendor').autocomplete({
  source: "./api/person-list.php?callback=?",
  minLength: 2,
  select: function(ev, ui) {
    $("#upload-form input[name='vendor']").val(ui.item.id);
  },
});
$(document).on('change', '.btn-file :file', function() {
      var input = $(this),
      numFiles = input.get(0).files ? input.get(0).files.length : 1,
      label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
      input.trigger('fileselect', [numFiles, label]);
});

$(document).ready(
  function() {
    $('.btn-file :file').on('fileselect', function(event, numFiles, label) {
      var input = $(this).parents('.input-group').find(':text'),
          log = numFiles > 1 ? numFiles + ' files selected' : label;
                
      if( input.length ) {
        input.val(log);
      } else {
        if( log ) alert(log);
      }
                
    });
});             
</script>
