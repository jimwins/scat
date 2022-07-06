"use strict";

class ScatWeb {
  // format number as $3.00 or ($3.00)
  amount (val) {
    if (typeof(val) == 'function') {
      val= val()
    }
    if (typeof(val) == 'undefined' || val == null) {
      return ''
    }
    if (typeof(val) == 'string') {
      val= parseFloat(val)
    }
    if (val < 0.0) {
      return '($' + Math.abs(val).toFixed(2) + ')'
    } else {
      return '$' + val.toFixed(2)
    }
  }

  ecommerce (event, parameters) {
    let func = () => {
      if (window.zaraz) {
        window.zaraz.ecommerce(event, parameters)
      }

      if (window.uetq) {
        switch (event) {
          case 'Product Added':
            window.uetq.push('event', 'add_to_cart', {
              'ecomm_prodid': parameters.product_id,
              'ecomm_pagetype': 'product',
              'ecomm_totalvalue': parameters.price * parameters.quantity,
              'revenue_value': parameters.price * parameters.quantity,
              'currency': parameters.currency,
              'items': [
                {
                  'id': parameters.product_id,
                  'quantity': parameters.quantity,
                  'price': parameters.price,
                },
              ]
            });
            break;

          case 'Product Viewed':
            window.uetq.push('event', '', {
              'ecomm_prodid': parameters.product_id,
              'ecomm_pagetype': 'product',
            });
            break;

          case 'Order Completed':
            window.uetq.push('event', 'purchase', {
              'revenue_value' : parameters.total,
              'currency' : parameters.currency,
            });
            break;

          case 'Cart Viewed':
          case 'Product List Viewed':
          case 'Products Searched':

          default:
            console.log(`No handling for ${event} with Bing, ignoring`)
        }
      }

      if (window.pintrk) {
        switch (event) {
          case 'Product Added':
            window.pintrk('track', 'addtocart', {
              'currency': parameters.currency,
              'line_items': [
                {
                  'product_id': parameters.product_id,
                  'product_quantity': parameters.quantity,
                  'product_price': parameters.price,
                },
              ]
            });
            break;

          case 'Product Viewed':
            window.pintrk('track', 'pageview', {
              'product_id': parameters.product_id,
            });
            break;

          case 'Order Completed':
            let items= parameters.products.map((x) => ({
              'product_id' : x.product_id,
              'product_quantity' : x.quantity,
              'product_price' : x.price,
            }));
            window.pintrk('track', 'checkout', {
              'value' : parameters.total,
              'currency' : parameters.currency,
              'line_items' : items,
            });
            break;

          case 'Cart Viewed':
          case 'Product List Viewed':
          case 'Products Searched':

          default:
            console.log(`No handling for ${event} with Pinterest, ignoring`)
        }
      }
    }

    if (window.document.readyState == 'complete') {
      func()
    } else {
      window.addEventListener('load', (event) => {
        func()
      })
    }
  }
}

let scat= new ScatWeb()
