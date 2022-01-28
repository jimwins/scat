var Scat= {};

/*
 * Call the server API, handle basic errors
 */

Scat.api= function (func, args, opts) {
  var url= '/api/' + func + '.php?callback=?';

  // http://stackoverflow.com/a/5175170
  var validated= function(jqXHR, validationFunction) {
    var def= new $.Deferred();
    var validate= function() {
        var result= validationFunction.apply(this, arguments);
        if (result) {
          // XXX is def the right first argument here? ¯\_(ツ)_/¯
          def.resolve.apply(def, arguments);
        } else {
          def.reject.apply(def, arguments);
        }
    }

    jqXHR.done(validate);
    return def;
  }

  var jqXHR= $.ajax($.extend({ dataType: "json", url: url, data: args },
                             opts));

  return validated(jqXHR, function(data) {
    if (data.error) {
      Scat.alert(data);
      return false;
    }
    return true;
  });
}

Scat.printDirect= function(name, options) {
  $.getJSON("/print/" + name + ".php?callback=?",
            options,
            function (data) {
              if (data.error) {
                Scat.alert(data);
                return;
              }
            });
}

// format number as $3.00 or ($3.00)
function amount(amount) {
  if (typeof(amount) == 'function') {
    amount= amount();
  }
  if (typeof(amount) == 'undefined' || amount == null) {
    return '';
  }
  if (typeof(amount) == 'string') {
    amount= parseFloat(amount);
  }
  if (amount < 0.0) {
    return '($' + Math.abs(amount).toFixed(2) + ')';
  } else {
    return '$' + amount.toFixed(2);
  }
}
Scat.amount= amount;

// display an error message
Scat.alert= function (data) {
  if (typeof data != "object") {
    data= { error: data };
  }

  if (!$('body').hasClass('modal-open')) {
    var title= data.title ? data.title : 'Error';
    var modal= $('<div class="modal fade"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button><h4 class="modal-title">' + title + '</h4></div><div class="modal-body"></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div></div></div>');

    $('.modal-body', modal).prepend(data.error);

    // Only display query stuff when $DEBUG
    if ($("#corner-banner") && data.explain) {
      $('.modal-body', modal).append($('<h5>' + data.explain + '</h5>' + '<pre class="pre-scrollable">' + data.query + '</pre>'));
    }

    modal.on('hidden.bs.modal', function() {
      $(this).remove();
    });

    modal.appendTo($('body')).modal();

    // Remove our current focus to avoid stacking errors
    document.activeElement.blur();

    return;
  }

  alert(data.error);
}

$.fn.select2.defaults.set( "theme", "bootstrap" );
