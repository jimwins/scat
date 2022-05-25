// format number as $3.00 or ($3.00)
function amount(val) {
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

function updateCart(details) {
  let form= document.getElementById('payment-form')
  form.setAttribute('disabled', '')

  return fetch('{{ url_for('update-cart') }}', {
    'method': 'POST',
    'headers' : {
      'Accept': 'application/json',
      'Content-type': 'application/json',
    },
    body: JSON.stringify(details)
  }).then((res) => {
    if (res.status >= 200 && res.status < 300) {
      return Promise.resolve(res)
    }
    return Promise.reject(new Error(res.statusText))
  }).then((res) => {
    return res.json()
  }).then((data) => {
    if (data.cart_html) {
      let template= document.createElement('template')
      template.innerHTML= data.cart_html.trim()
      let cart= document.getElementById('cart')
      cart.replaceWith(template.content.firstChild)
    }
    if (data.shipping_options_html) {
      let template= document.createElement('template')
      template.innerHTML= data.shipping_options_html.trim()
      let shipping_options= document.getElementById('shipping-options')
      shipping_options.replaceWith(template.content.firstChild)
      listenToShippingMethod()
    }
    if (data.loyalty_html) {
      let template= document.createElement('template')
      template.innerHTML= data.loyalty_html.trim()
      let loyalty= document.getElementById('loyalty')
      loyalty.replaceWith(template.content.firstChild)
      listenToShippingMethod()
    }

    if (data.ready_for_payment) {
      enablePayButtons(data.due);
    } else {
      disablePayButtons();
    }
  })
  .finally(() => {
    form.removeAttribute('disabled')
  })
}

let timeout= null;

let form= document.getElementById('payment-form')
form.addEventListener('click', (ev) => {
  let action= ev.target.closest('[data-action]')
  if (!action) return;
  if (action.dataset.action == 'apply-reward') {
    updateCart({ reward: action.dataset.id })
  }
  if (action.dataset.action == 'remove-reward') {
    updateCart({ reward: 0 })
  }
})

let handleShippingMethodChange= (event) => {
  const method= event.currentTarget.value;
  clearTimeout(timeout);
  timeout= setTimeout(() => {
    updateCart({ method : method })
  }, 500);
}

function listenToShippingMethod() {
  let form= document.getElementById('payment-form')
  if (!form.elements['shipping_method']) return;

  form.elements['shipping_method'].forEach((el) => {
    el.addEventListener('change', handleShippingMethodChange)
  })
}

listenToShippingMethod()

