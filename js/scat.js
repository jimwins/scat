var Scat= {};

/*
 * Call the server API, handle basic errors
 */

Scat.api= function (func, args, opts) {
  var url= 'api/' + func + '.php?callback=?';

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

Scat.dialog= function (name) {
  var url= 'ui/' + name + '.html';

  // XXX handle error
  return $.ajax({ url: url, cache: false });
  /*
    var panel= $(html);

    panel.on('hidden.bs.modal', function() {
      $(this).remove();
    });

    person.error= '';

    personModel= ko.mapping.fromJS(person);

    personModel.savePerson= function(place, ev) {
      var person= ko.mapping.toJS(personModel);
      Scat.api(person.id ? 'person-update' : 'person-add', person)
          .done(function (data) {
            if (person.id) {
              viewModel.load(data);
            } else {
              Txn.updatePerson(Txn.id(), data.person);
            }
            $(place).closest('.modal').modal('hide');
          });
    }

    ko.applyBindings(personModel, panel[0]);

    panel.appendTo($('body')).modal();
  });
  */
}

Scat.print= function(name, options) {
  $('#scat-print').remove();

  var url= 'print/' + name + '.php?' + $.param(options);
  var lpr= $('<iframe id="scat-print" src="' + url + '"></iframe>').hide();

  lpr.on("load", function() {
    /* If we got JSON, we printed directly */
    if (this.contentDocument.contentType != 'application/json') {
      this.contentWindow.print();
    }
  });

  $('body').append(lpr);

  return false;
}

Scat.printDirect= function(name, options) {
  $.getJSON("print/" + name + ".php?callback=?",
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

Scat.getFocusedElement= function() {
  var elem = document.activeElement;
  return $( elem &&
            ( elem.type || elem.href ||
              elem.className.includes("select2-selection") ) ? elem : [] );
};

$(function() {
  $(document).keydown(function(ev) {
    if (ev.keyCode == 16 || ev.keyCode == 17
        || ev.keyCode == 18 || ev.keyCode == 91
        || ev.metaKey || ev.altKey || ev.ctrlKey) {
      return true;
    }
    var el= Scat.getFocusedElement();
    if (!el.length &&
        !$('#simplemodal-overlay').length && !$('.modal-backdrop').length) {
      var inp= $('.autofocus', this);
      if (ev.keyCode != 13) {
        inp.val('');
      }
      inp.focus();
    }
  });
});

// http://blog.fawnanddoug.com/2012/05/inline-editor-custom-binding-for.html
ko.bindingHandlers.jeditable= {
  init: function(element, valueAccessor, allBindingsAccessor) {
    // get the options that were passed in
    var options= allBindingsAccessor().jeditableOptions || {};
          
    // "submit" should be the default onblur action like regular ko controls
    if (!options.onblur) {
      options.onblur= 'submit';
    }

    // allow the editable function to be set as an option
    if (!options.onupdate) {
      options.onupdate= function(value, params) {
        valueAccessor()(value);
        return value;
      }
    }

    // set the value on submit and pass the editable the options
    $(element).editable(options.onupdate, options);
 
    //handle disposal (if KO removes by the template binding)
    ko.utils.domNodeDisposal.addDisposeCallback(element, function() {
      //$(element).editable("destroy");
    });
 
  },
      
  //update the control when the view model changes
  update: function(element, valueAccessor, allBindingsAccessor) {
    // get the options that were passed in
    var options= allBindingsAccessor().jeditableOptions || {};

    var value= ko.utils.unwrapObservable(valueAccessor());
    if (options.ondisplay) {
      value= options.ondisplay(value);
    }
    $(element).html(value);
  }
};

$.editable.addInputType('select2', {
    element : function(settings, original) {
        var input= $.editable.types.select.element.apply(this, [settings, original]);
        return(input);
    },
    plugin : function(settings, original) {
        var select= $("select", this);
        select.select2(settings.select2);
        select.on('select2:close', function(e) {
          select.removeClass('select2-container-active');
          select.blur();
        });
        return (select);
    },
    content : function(data, settings, original) {
      if (!settings.submit) {
        // XXX add our initial option
        var form= this;
        $(this).find('select').change(function() {
          form.submit();
        });
      }
    },
});

$.fn.editable.defaults.inputcssclass= 'form-control';
$.fn.editable.defaults.cancelcssclass= 'btn btn-default';
$.fn.editable.defaults.submitcssclass= 'btn btn-primary';
$.fn.editable.defaults.width=  'none';
$.fn.editable.defaults.height= 'none';

$.fn.select2.defaults.set( "theme", "bootstrap" );
