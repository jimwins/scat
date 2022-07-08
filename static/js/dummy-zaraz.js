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

/* Dummy Zaraz */
let zaraz= new Zaraz()
window.zaraz= new Zaraz()

/* Dummy Microsoft */
window.uetq= [];

/* Dummy Pinterest */
class Pinterest {
  pintrk (action, event, parameters) {
    console.log("Pinterest %s: %s %O", action, event, parameters)
  }
}

window.pintrk= new Pinterest()
