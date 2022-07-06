/* Just a simple Zaraz dummy that spits stuff to console */
"use strict";

class Zaraz {
  set (key, value, options= null) {
    console.log("Zaraz Set: %s = %s %O", key, value, options)
  }

  track (event, parameters= null) {
    console.log("Zaraz Track: %s %O", event, parameters)
  }

  ecommerce (event, parameters) {
    console.log("Zaraz Ecommerce: %s %O", event, parameters)
  }
}

let zaraz= new Zaraz()
window.zaraz= zaraz
