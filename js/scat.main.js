/* Scat functionality */

"use strict";

class ScatUtils {

  htmlToElement (html) {
    let template= document.createElement('template')
    template.innerHTML= html.trim()
    return template.content.firstChild
  }

  // Pop up a dialog
  dialog (from, name, data= null) {
    let url= name

    let params= new URLSearchParams(data)
    if (params.keys().length) {
      url+= (url.match(/\?/) ? '&' : '?') + params
    }

    if (from.disabled) return false
    from.disabled= true

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
      console.log("Loaded '" + url + "'.")
      let modal= this.htmlToElement(text)
      document.body.insertAdjacentElement('beforeend', modal)
      $(modal).on('show.bs.modal', function(e) {
        // Re-inject the script to get it to execute
        let code= this.getElementsByTagName('script')[0].innerHTML
        let script= document.createElement('script')
        script.modal= this
        script.appendChild(document.createTextNode(code))
        this.appendChild(script).parentNode.removeChild(script)
        /* Attach dialog to each object with event handler */
        this.querySelectorAll('*').forEach((el) => {
          if (typeof el.onclick === 'function' ||
              typeof el.onsubmit === 'function') {
            el.dialog= this
          }
        })
      })
      $(modal).on('hidden.bs.modal', function(e) {
        $(this.script).remove()
        $(this).remove()
      });
      $(modal).modal()
      from.disabled= false
      return Promise.resolve($(modal))
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

  printDocument (name, options) {
    let el= document.getElementById('scat-print')
    if (el) {
      el.remove()
    }

    let url= '/print/' + name + '.php?' + $.param(options)
    let text= '<iframe id="scat-print" src="' + url + '"></iframe>'

    let lpr= this.htmlToElement(text)
    lpr.style.display= 'none'
    lpr.addEventListener('load', (ev) => {
      /* If we got JSON, we printed directly */
      if (ev.target.contentDocument.contentType != 'application/json') {
        ev.target.contentWindow.print()
      }
    })

    document.body.insertAdjacentElement('beforeend', lpr)
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

  call (url, args, opts) {
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
    .then((response) => {
      if (response.status >= 200 && response.status < 300) {
        return Promise.resolve(response)
      }
      if (res.headers.get('Content-type').indexOf("application/json") !== -1) {
        return res.json()
                  .then((data) => {
                    return Promise.reject(new Error(data.exception[0].message))
                  })
      }
      return Promise.reject(new Error(response.statusText))
    })
    .then((response) => {
      /* Look for some headers to change title, reload page sections */
      let title= response.headers.get('X-Scat-Title')
      if (title) {
        window.document.title= title
      }

      if (response.headers.has('X-Scat-Reload')) {
        let tokens= response.headers.get('X-Scat-Reload')
        tokens.split(',').forEach((token) => {
          console.log("Requested reload of " + token.trim())
          let el= document.getElementById(token.trim())
          if (el && el.hasAttribute('data-reload')) {
            let reload= el.getAttribute('data-reload')
            fetch(reload)
            .then((res) => {
              if (res.status >= 200 && res.status < 300) {
                return Promise.resolve(res)
              }
              return Promise.reject(new Error(res.statusText))
            })
            .then((res) => res.text())
            .then((text) => {
              // Yes, we trust this HTML
              el.innerHTML= text
            })
          } else {
            console.error(`Could not find "${token}" to be replaced`)
          }
        })
      }
      return response
    })
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
    .then((res) => {
      if (res.status >= 200 && res.status < 300) {
        return Promise.resolve(res)
      }
      if (res.headers.get('Content-type').indexOf("application/json") !== -1) {
        return res.json()
                  .then((data) => {
                    return Promise.reject(new Error(data.exception[0].message))
                  })
      }
      return Promise.reject(new Error(res.statusText))
    })
  }

  api (func, args, opts) {
    let url= '/api/' + func + '.php';
    return this.call(url, args, opts)
  }

  print (url, args) {
    const formData= args instanceof FormData ? args : new FormData()

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
    .then((response) => {
      if (response.status >= 200 && response.status < 300) {
        return Promise.resolve(response)
      }
      return Promise.reject(new Error(response.statusText))
    })
    .then((res) => {
      if (res.headers.get('Content-type').indexOf("application/pdf") != 1) {
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
            ev.target.contentWindow.print()
          })
          // holy moly, this is magic!
          lpr.src= URL.createObjectURL(blob)
          document.body.appendChild(lpr)
        })
      } else
      if (res.headers.get('Content-type').indexOf("application/json") == -1) {
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
          scat.alert('success', data.message)
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
}

let scat= new ScatUtils()
