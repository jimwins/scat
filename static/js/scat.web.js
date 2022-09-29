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
      if (window.dataLayer) {
        window.dataLayer.push({ ecommerce: null }); /* clear */

        switch (event) {
          case 'Product List Viewed': {
            let items= parameters.products.map((x) => ({
              'item_id' : x.product_id,
              'name' : x.name,
              'price' : x.price,
              'currency' : x.currency,
              'item_brand' : x.brand,
              'item_category' : x.category
            }));
            window.dataLayer.push({
              'event': 'view_item_list',
              'ecommerce': {
                'item_list_name': parameters.name,
                'items': items
              }
            });
            break;
          }

          case 'Product Viewed':
            window.dataLayer.push({
              'event': 'view_item',
              'ecommerce': {
                'items': [
                  {
                    'item_id': parameters.product_id,
                    'item_name': parameters.name,
                    'item_category': parameters.category,
                    'item_brand': parameters.brand,
                    'price': parameters.price,
                    'currency': parameters.currency
                  }
                ]
              }
            });
            break;

          case 'Product Added':
            window.dataLayer.push({
              'event': 'add_to_cart',
              'ecommerce': {
                'items': [
                  {
                    'item_id': parameters.product_id,
                    'item_name': parameters.name,
                    'item_category': parameters.category,
                    'item_brand': parameters.brand,
                    'price': parameters.price,
                    'quantity': parameters.quantity,
                    'currency': parameters.currency
                  }
                ]
              }
            });
            break;

          case 'Product Removed':
            window.dataLayer.push({
              'event': 'remove_from_cart',
              'ecommerce': {
                'items': [
                  {
                    'item_id': parameters.product_id,
                    'quantity': parameters.quantity,
                  }
                ]
              }
            });
            break;

          case 'Checkout Started': {
            let items= parameters.products.map((x) => ({
              'item_id' : x.product_id,
              'item_name' : x.name,
              'quantity' : x.quantity,
              'price' : x.price,
              'currency' : x.currency,
              'brand' : x.brand,
              'category' : x.category,
            }));
            window.dataLayer.push({
              'event': 'begin_checkout',
              'ecommerce': {
                'items': items
              }
            });
            break;
          }

          case 'Order Completed': {
            let items= parameters.products.map((x) => ({
              'item_id' : x.product_id,
              'item_name' : x.name,
              'quantity' : x.quantity,
              'price' : x.price,
              'currency' : x.currency,
              'brand' : x.brand,
              'category' : x.category,
            }));
            window.dataLayer.push({
              'event': 'purchase',
              'ecommerce': {
                'transaction_id': parameters.order_id,
                'value': parameters.subtotal,
                'tax': parameters.tax,
                'shipping': parameters.shipping,
                'currency': parameters.currency,
                'items': items
              }
            });
            break;
          }

          default:
            console.log(`No handling for ${event} with GA, ignoring`)
        }
      } /* end dataLayer */

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

          case 'Cart Viewed': {
            let item_ids= parameters.products.map(x => x.product_id);
            let items= parameters.products.map((x) => ({
              'id' : x.product_id,
              'quantity' : x.quantity,
              'price' : x.price,
            }));

            window.uetq.push('event', '', {
              'ecomm_prodid' : item_ids,
              'ecomm_pagetype' : 'cart',
              'ecomm_totalvalue' : parameters.total,
              'revenue_value' : parameters.total,
              'currency' : parameters.currency,
              'items' : items,
            });
            break;
          }

          case 'Checkout Started':
            window.uetq.push('event', 'begin_checkout', {
              'revenue_value' : parameters.total,
              'currency' : parameters.currency,
            });
            break;

          case 'Order Completed': {
            let item_ids= parameters.products.map(x => x.product_id);
            let items= parameters.products.map((x) => ({
              'id' : x.product_id,
              'quantity' : x.quantity,
              'price' : x.price,
            }));

            window.uetq.push('event', 'purchase', {
              'transaction_id' : parameters.order_id,
              'ecomm_prodid' : item_ids,
              'ecomm_pagetype' : 'purchase',
              'ecomm_totalvalue' : parameters.total,
              'revenue_value' : parameters.total,
              'currency' : parameters.currency,
              'items' : items,
            });
            break;
          }

          case 'Product List Viewed': {
            let item_ids= parameters.products.map(x => x.product_id);
            window.uetq.push('event', '', {
              'ecomm_category' : parameters.name,
              'ecomm_prodid' : item_ids,
              'ecomm_pagetype' : 'category',
            });
            break;
          }

          case 'Products Searched':
            /* Not used yet because of how our search works. */

          default:
            console.log(`No handling for ${event} with Bing, ignoring`)
        }
      } /* end uetq */

      if (window.fbq) {
        switch (event) {
          case 'Product List Viewed': {
            let item_ids= parameters.products.map(x => x.product_id);
            window.fbq('track', 'ViewContent', {
              'content_type': 'product',
              'content_ids': item_ids,
            });
            break;
          }

          case 'Product Viewed':
            window.fbq('track', 'ViewContent', {
              'content_type': 'product',
              'content_ids':  parameters.product_id
            });
            break;

          case 'Product Added':
            window.fbq('track', 'AddToCart', {
              'content_type': 'product',
              'content_ids':  parameters.product_id,
              'currency': parameters.currency,
              'value': parameters.price * parameters.quantity,
            });
            break;

          case 'Checkout Started':
            let items= parameters.products.map((x) => ({
              'id' : x.product_id,
              'quantity' : x.quantity,
            }));
            window.fbq('track', 'InitiateCheckout', {
              'content_type': 'product',
              'contents': items,
              'num_items': items.length,
              'currency': parameters.currency,
              'value': parameters.total
            });
            break;

          case 'Order Completed':
            let contents= parameters.products.map((x) => ({
              'id' : x.product_id,
              'quantity' : x.quantity,
            }));
            window.fbq('track', 'Purchase', {
              'content_type': 'product',
              'contents': contents,
              'num_items': contents.length,
              'currency': parameters.currency,
              'value': parameters.total
            });
            break;

          default:
            console.log(`No handling for ${event} with FB, ignoring`)
        }
      } /* end fbq */

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
            window.pintrk('track', 'pagevisit', {
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
      } /* end pintrk */
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
