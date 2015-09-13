<form role="form" method="get" action="items.php">
  <div class="input-group">
    <span class="input-group-btn">
      <input type="submit" class="btn btn-primary" value="Search">
    </span>
    <input type="text" class="autofocus form-control" size="60"
           id="search" name="search" data-bind="value: search"
           placeholder="Enter keywords or scan barcode"
           autocorrect="off" autocapitalize="off"
           value="<?=ashtml($search)?>">
    <span class="input-group-addon">
      <label>
        <input type="checkbox" value="1" name="all" data-bind="checked: all"
               <?=(int)$_REQUEST['all'] ? 'checked' : ''?>>
          Include inactive?
        </label>
    </span>
<?if ($_REQUEST['search']) {?>
    <span class="input-group-btn">
      <button id="save"
              data-saved="<?=(int)$_REQUEST['saved']?>"
              class="btn btn-default">
        <i class="fa fa-floppy-o"></i>
      </button>
    </span>
<script>
$('#save').on('click', function(ev) {
  ev.preventDefault();

  var data= {};

  data.search= $("input[name='search']").val();

  saved= $(ev.target).data('saved');

  if (!saved) {
    data.name= prompt("Save search as:");

    if (!data.name) {
      return;
    }
  } else {
    data.id= saved;
  }

  $.getJSON("api/search-save.php?callback=?",
            data,
            function (data) {
              if (data.error) {
                displayError(data);
                return;
              }
              $('#save').data('saved', data.id);
            });
});
</script>
<?}?>
  </div>
</form>
<br>
<br>
