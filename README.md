Scat is a web-based Point-of-Sale (POS) system.

Scat requires PHP 8.1 or later and MySQL 8.0 or later.

## INSTALL

You shouldn't try to run this "in production" unless you know what you're doing, but you can get it running in a test/demo environment using the included docker-compose configuration.

 * clone the repository
 * `docker-compose up`
 * connect to `http://localhost:5080` (or the server name if it’s not running on your local machine)
 * click the “Set up the database” button
 * click the "Load some sample data" button
 * click the “Return to Scat” button
 * start poking around

## Dependencies

See `composer.json` for most of the dependencies, but here are some notable and/or bundled ones.

Scat uses the jQuery Javascript library.
  http://jquery.com/

Scat uses the Bootstrap front-end framework:
  http://getbootstrap.com/

Scat uses Fork Awesome, a fork of the iconic font and CSS toolkit:
  http://forkaweso.me/

Scat includes bootstrap-datepicker.js:
  https://github.com/eternicode/bootstrap-datepicker/

Scat includes Mousetrap for handling keyboard shortcuts:
  https://craig.is/killing/mice

Scat includes Chart.js for charting:
  http://chartjs.com/

Scat includes FPDF for PDF generation:
  http://www.fpdf.org/

Scat includes BarCode Coder Library for barcode generation:
  http://barcode-coder.com/

Scat uses PhpSpreadSheet for writing Excel spreadsheets:
  https://phpspreadsheet.readthedocs.io/

Scat includes X-editable:
 https://vitalets.github.io/x-editable/index.html

Scat uses the Titi minimalist database toolkit:
  https://github.com/jimwins/titi

See the LICENSE file for licensing information.
