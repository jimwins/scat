<?
require 'scat.php';

head('Gift Cards', true);
?>
<form role="form">
  <div class="form-group">
    <label for="card">Card</label>
    <input type="text" class="autofocus form-control" id="card"
           placeholder="Scan or enter card">
  </div>
  <div class="form-group">
    <label for="amount">Amount</label>
    <input type="text" class="form-control" id="amount"
           placeholder="$0.00">
  </div>
  <button id="check" type="submit" class="btn btn-primary">Check</button>
  <button id="create" class="btn btn-default">Create</button>
  <button id="add" class="btn btn-default">Add</button>
  <button id="spend" class="btn btn-default">Spend</button>
  <button id="print" class="btn btn-default">Print</button>
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
  return false;
});
$('#create').on('click', function() {
  var amount= $('#amount').val();
  $.getJSON("<?=GIFT_BACKEND?>/create.php?callback=?",
            { balance: amount },
            function (data) {
              $('#result').text('');
              if (data.error) {
                $('#result').append(data.error);
              } else {
                $('#result').append(data.success);
                $('#amount').val(data.balance);
                $('#card').val(data.card);
              }
            });
  return false;
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
  return false;
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
  return false;
});
$('#print').on('click', function() {
  var card= $('#card').val();
  $.getJSON("<?=GIFT_BACKEND?>/check-balance.php?callback=?",
            { card: card },
            function (data) {
              $('#result').text('');
              if (data.error) {
                $('#result').append(data.error);
              } else {
                printGiftCard(data.card, data.balance, data.latest);
                $('#result').append('Card printed showing $' + data.balance + ' balance.');
              }
            });
  return false;
});
function printGiftCard(card, balance, issued) {
  var lpr= $('<iframe id="giftcard" src="print/gift-card.php?card=' + card + '&amp;balance=' + balance + '&amp;issued=' + issued + '"></iframe>').hide();
  $("#giftcard").remove();
  lpr.on("load", function() {
    this.contentWindow.print();
  });
  $('body').append(lpr);
  return false;
}
</script>
<?
foot();
