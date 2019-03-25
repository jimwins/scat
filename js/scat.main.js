/* Scat functionality */

"use strict";

class ScatUtils {

  htmlToElement (html) {
    let template= document.createElement('template')
    template.innerHTML= html.trim()
    return template.content.firstChild
  }

  // Pop up a dialog
  dialog (from, name, data= {}) {
    let url= name

    if (from.disabled) return false
    from.disabled= true

    fetch(url)
      .then(res => {
        if (!res.ok) {
          throw new Error('Network response was not ok.')
        }

        res.text().then(text => {
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
        })
      })
      .catch (error => {
        console.log('There has been a problem with your fetch operation: ',
                    error.message)
      })
  }
}

let scat= new ScatUtils()
