<form role="form" method="get" action="/catalog/search">
  <div class="input-group">
    <span class="input-group-btn">
      <input type="submit" class="btn btn-primary" value="Search">
    </span>
    <input type="search" class="autofocus form-control" size="60"
           id="q" name="q"
           placeholder="Enter keywords or scan barcode"
           autocomplete="off" autocorrect="off" autocapitalize="off"
           spellcheck="false"
           value="<?=ashtml($q)?>">
    <span class="input-group-addon checkbox">
      <label>
        <input type="checkbox" value="1" name="all" data-bind="checked: all"
               <?=(int)$_REQUEST['all'] ? 'checked' : ''?>>
        <span class="hidden-xs">Include inactive?</span>
        <span class="visible-xs">All?</span>
      </label>
    </span>
  </div>
</form>
<br>
