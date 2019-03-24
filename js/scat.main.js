/* Scat functionality */

"use strict";

class ScatUtils {

  htmlToElement (html) {
    let template= document.createElement('template');
    template.innerHTML= html.trim();
    return template.content.firstChild;
  }

  // Pop up a dialog
  dialog (name, data= {}) {
    let url= name;

    fetch(url)
      .then(res => {
        if (!res.ok) {
          throw new Error('Network response was not ok.');
        }

        res.text().then(text => {
          console.log("Loaded '" + url + "'.");
          let modal = this.htmlToElement(text);
          document.body.insertAdjacentElement('beforeend', modal);
          $(modal).modal();
        });
      })
      .catch (error => {
        console.log('There has been a problem with your fetch operation: ',
                    error.message);
      });
  }
}

let scat= new ScatUtils();
