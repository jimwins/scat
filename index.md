## Scat POS

Scat POS is a web-based point-of-sale system. It is built with [PHP](https://www.php.net/), [MySQL](https://www.mysql.com/), and [SphinxSearch](http://sphinxsearch.com).

It is currently a very rough work-in-progress and not suitable for use by anyone. (That said, it has been used by [Raw Materials Art Supplies](https://rawmaterialsla.com/) since 2012.)

### Current Status

Using the included `docker-compose.yml`, it's possible to get this up and running and maybe sort of working, at least enough to poke around. The steps:

* clone the repository
* `docker-compose up`
* connect to `http://localhost:5080` (or the server name if it's not running on your local machine)
* click the "Set up the database" button
* click the "Return to Scat" button
* start poking around

Work will continue on cleaning up the systems, removing dependencies on services that can be made optional, and otherwise making it possible that someone else could use it.

You can [read various blog posts about its development](https://trainedmonkey.com/tag/scat).
