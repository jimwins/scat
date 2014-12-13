<?
require 'scat.php';

head('Gift Cards');
?>
<form role="form" action="javascript: return false">
  <div class="form-group">
    <label for="card">Card</label>
    <input type="text" class="form-control" id="card"
           placeholder="Scan or enter card">
  </div>
  <div class="form-group">
    <label for="amount">Amount</label>
    <input type="text" class="form-control" id="amount"
           placeholder="$0.00">
  </div>
  <button id="check" type="submit" class="btn btn-primary">Check</button>
  <button id="activate" class="btn btn-default">Activate</button>
  <button id="add" class="btn btn-default">Add</button>
  <button id="spend" class="btn btn-default">Spend</button>
</form>
<br>
<div id="result" class="alert alert-success">
</div>

<script>
$('#check').on('click', function() {
  var card= $('#card').val();
  $.getJSON("<?=GIFT_BACKEND?>/check-balance.php?callback=?",
            { card: card },
            function (data) {
              $('#result').text('');
              if (data.error) {
                $('#result').append(data.error);
              } else if (data.balance > 0) {
                $('#result').append('This card has a balance of $' + data.balance + ' and was last used or credited on ' + data.latest + '.');
              } else {
                $('#result').append('This card is active, but has no balance.');
              }
            });
});
$('#activate').on('click', function() {
  var card= $('#card').val();
  var amount= $('#amount').val();
  $.getJSON("<?=GIFT_BACKEND?>/activate.php?callback=?",
            { card: card, balance: amount },
            function (data) {
              $('#result').text('');
              if (data.error) {
                $('#result').append(data.error);
              } else {
                $('#result').append(data.success);
              }
            });
});
$('#add').on('click', function() {
  var card= $('#card').val();
  var amount= $('#amount').val();
  $.getJSON("<?=GIFT_BACKEND?>/add-txn.php?callback=?",
            { card: card, amount: amount },
            function (data) {
              $('#result').text('');
              if (data.error) {
                $('#result').append(data.error);
              } else {
                $('#result').append(data.success);
              }
            });
});
$('#spend').on('click', function() {
  var card= $('#card').val();
  var amount= $('#amount').val();
  $.getJSON("<?=GIFT_BACKEND?>/add-txn.php?callback=?",
            { card: card, amount: -amount },
            function (data) {
              $('#result').text('');
              if (data.error) {
                $('#result').append(data.error);
              } else {
                $('#result').append(data.success);
              }
            });
});
</script>
<?
foot();
