/* Scat functionality */

"use strict";

class ScatUtils {

  htmlToElement (html) {
    let template= document.createElement('template')
    template.innerHTML= html.trim()
    return template.content.firstChild
  }

  // Pop up a dialog
  dialog (name, data= null, action= null) {
    let url= name

    let params= new URLSearchParams(data)
    if (params.toString().length) {
      url+= (url.match(/\?/) ? '&' : '?') + params.toString()
    }

    return fetch(url, {
      headers: {
        'Accept': 'application/vnd.scat.dialog+html'
      }
    })
    .then((response) => {
      if (response.status >= 200 && response.status < 300) {
        return Promise.resolve(response)
      }
      return Promise.reject(new Error(response.statusText))
    })
    .then((res) => { return res.text() })
    .then((text) => {
      return new Promise((resolve, reject) => {
        let modal= this.htmlToElement(text)
        modal.resolution= null
        document.body.insertAdjacentElement('beforeend', modal)
        $(modal).on('show.bs.modal', function(e) {
          // Re-inject the script to get it to execute
          let code= this.getElementsByTagName('script')[0].innerHTML
          let script= document.createElement('script')
          script.modal= this
          script.appendChild(document.createTextNode(code))
          this.appendChild(script).parentNode.removeChild(script)
          /* Attach dialog to each object with onsubmit handler */
          this.querySelectorAll('*').forEach((el) => {
            if (typeof el.onsubmit === 'function') {
              el.dialog= this
            }
          })
        })
        $(modal).on('hidden.bs.modal', function(e) {
          $(this.script).remove()
          $(this).remove()
          resolve(modal.resolution)
        });
        $(modal).modal()
        if (action) {
          action(modal);
        }
      })
    })
    .catch((err) => {
      scat.alert('danger', err.message)
    })
  }

  generateSlug (...parts) {
    return import('/js/url_slug.js')
      .then(m => {
          return m.url_slug(parts.join('-'),
                            {
                              replacements : {
                                '&': 'and',
                                '#': 'hashbrown-'
                              }
                            })
      })
  }

  // TODO remove when done with /print/
  printDocument (name, options) {
    return this.print('/print/' + name + '.php', options)
  }

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

  _handleResponse (res) {
    if (res.status >= 200 && res.status < 300) {
      return Promise.resolve(res)
    }
    if (res.headers.get('Content-type').indexOf("application/json") !== -1) {
      return res.json()
                .then((data) => {
                  return Promise.reject(new Error(data.message))
                })
    }
    return Promise.reject(new Error(res.statusText))
  }

  post (url, args, opts) {
    const formData= args instanceof FormData ? args : new FormData()

    // XXX should verify that url is not remove since we trust content

    if (!(args instanceof FormData)) {
      for (let prop in args) {
        formData.append(prop, args[prop])
      }
    }

    return fetch(url, {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      body: formData
    })
    .then((response) => this._handleResponse(response))
  }

  call (url, args, opts) {
    return this.post(url, args, opts)
  }

  get (url, args, opts) {
    const formData= args instanceof FormData ? args : new FormData()

    // XXX should verify that url is not remove since we trust content

    if (!(args instanceof FormData)) {
      for (let prop in args) {
        formData.append(prop, args[prop])
      }
    }

    let query= new URLSearchParams(formData).toString()
    return fetch(url + (query ? '?' + query : ''),
      Object.assign({ method: 'GET' }, opts)
    )
    .then((response) => this._handleResponse(response))
  }

  patch (url, args, opts) {
    const formData= args instanceof FormData ? args : new FormData()

    // XXX should verify that url is not remove since we trust content

    if (!(args instanceof FormData)) {
      for (let prop in args) {
        formData.append(prop, args[prop])
      }
    }

    return fetch(url, {
      method: 'PATCH',
      headers: {
        'Content-type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(Object.fromEntries(formData))
    })
    .then((response) => this._handleResponse(response))
  }

  delete (url, args, opts) {
    const formData= args instanceof FormData ? args : new FormData()

    // XXX should verify that url is not remove since we trust content

    if (!(args instanceof FormData)) {
      for (let prop in args) {
        formData.append(prop, args[prop])
      }
    }

    return fetch(url, {
      method: 'DELETE',
      headers: {
        'Content-type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(Object.fromEntries(formData))
    })
    .then((response) => this._handleResponse(response))
  }

  api (func, args, opts) {
    let url= '/api/' + func + '.php';
    return this.post(url, args, opts)
  }

  print (url, args) {
    const formData= args instanceof FormData ? args : new FormData()

    if (!(args instanceof FormData)) {
      for (let prop in args) {
        formData.append(prop, args[prop])
      }
    }

    return scat.post(url, formData)
    .then((res) => {
      if (res.headers.get('Content-type').includes("application/pdf")) {
        res.blob().then((blob) => {
          /* XXX
           * This doesn't work with Firefox, because it uses PDF.js
           * internally to do the rendering, which doesn't render when the
           * iframe is not visible and isn't even ready to print when the load
           * event fires when it is visible. ¯\_(ツ)_/¯
           */
          let lpr= document.createElement('iframe')
          lpr.style.display= 'none'
          lpr.addEventListener('load', (ev) => {
            setTimeout((() => ev.target.contentWindow.print()), 500)
          })
          // holy moly, this is magic!
          lpr.src= URL.createObjectURL(blob)
          document.body.appendChild(lpr)
        })
      } else
      if (!res.headers.get('Content-type').includes("application/json")) {
        res.text().then((html) => {
          let lpr= document.createElement('iframe')
          lpr.style.display= 'none'
          lpr.addEventListener('load', (ev) => {
            ev.target.contentWindow.print()
          })
          lpr.srcdoc= html
          document.body.appendChild(lpr)
        })
      } else {
        res.json().then((data) => {
          scat.alert('success', data.result)
        })
      }
    })
  }

  alert (level, title, message= undefined, timeOut= undefined) {
    let holder= document.getElementById('notification-holder')

    if (!holder) {
      window.alert(message)
      return
    }

    // given only one of message or title, use it as message
    if (message === undefined) {
      message= title
      title= ""
    }

    if (timeOut === undefined) {
      timeOut= ['success','info','warning'].includes(level) ? 3000 : 0;
    }

    let text= `<div class="alert alert-${level}" role="alert">
                 <button type="button" class="close"
                         data-dismiss="alert" aria-label="Close">
                   <span aria-hidden="true">&times;</span>
                 </button>
                 <strong>${title}</strong>
                 ${message}
               </div>`

    let alert= this.htmlToElement(text)
    alert.addEventListener('transitionend', (ev) => {
      ev.target.remove()
    })

    if (timeOut) {
      setTimeout(() => {
        alert.classList.add('fade-out')
      }, timeOut)
    }

    holder.appendChild(alert)
  }

  reload (...blocks) {
    blocks.forEach((block) => {
      let reload= document.querySelector(`[data-reload=${block}]`)
      if (reload) {
        return fetch(window.location.href + '?block=' + block)
        .then((res) => {
          return res.text()
        })
        .then ((text) => {
          let html= scat.htmlToElement(text)
          // force panels open
          if (html.classList.contains('collapse'))
            html.classList.add('in')
          reload.children[0].replaceWith(html)
        })
      }
    })
  }

  handleAction (eventName, action, func) {
    document.addEventListener(eventName, (ev) => {
      let act= ev.target.closest('[data-action]')
      if (act && act.getAttribute('data-action') === action) {
        ev.stopPropagation(); ev.preventDefault();

        if (act.disabled) return;
        act.disabled= true;

        let old= '';
        let icon= act.querySelector('i.fa')
        if (icon) {
          let newIcon= 'fa fa-spinner fa-spin'
          if (icon.classList.contains('fa-fw')) {
            newIcon+= " fa-fw"
          }
          old= icon.classList.value
          icon.classList.value= newIcon
        }

        func.call(this, act).finally(() => {
          if (icon) {
            icon.classList.value= old
          }
          act.disabled= false
        })
      }
    })
  }

  // from https://advancedweb.hu/how-to-serialize-calls-to-an-async-function/
  serialize (fn) {
    let queue= Promise.resolve();
    return (...args) => {
      const res= queue.then(() => fn(...args));
      queue= res.catch(() => {});
      return res;
    };
  }

  handleQueuedAction (eventName, action, func) {
    document.addEventListener(eventName, (ev) => {
      let act= ev.target.closest('[data-action]')
      if (act && act.getAttribute('data-action') === action) {
        ev.stopPropagation(); ev.preventDefault();

        let icon= act.querySelector('i.fa')
        if (icon) {
          let newIcon= 'fa fa-spinner fa-spin'
          if (icon.classList.contains('fa-fw')) {
            newIcon+= " fa-fw"
          }
          if (!icon.old) {
            icon.old= icon.classList.value
          }
          icon.classList.value= newIcon
        }

        let fn= this.serialize((() => {
          func.call(this, act).finally(() => {
            if (icon) {
              icon.classList.value= icon.old;
            }
          })
        })());

        return fn();
      }
    })
  }
}

let scat= new ScatUtils()
