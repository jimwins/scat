<?php
namespace Scat\Service;

class Data
{
  private $dsn, $options;

  public function __construct($config) {
    $this->dsn= $config['dsn'];
    $this->options= $config['options'];

    /* Configure Idiorm */
    \ORM::configure($this->dsn);
    foreach ($this->options as $option => $value) {
      \ORM::configure($option, $value);
    }
    \ORM::configure('driver_options', [
      \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
    ]);

    /* Always want to throw exceptions for errors */
    \ORM::configure('error_mode', \PDO::ERRMODE_EXCEPTION);

    /* ... and Paris */
    \Model::$auto_prefix_models= '\\Scat\\Model\\';
    \Model::$short_table_names= true;

    // TODO use a logger service and always log at debug level?
    if ($GLOBALS['DEBUG'] || $GLOBALS['ORM_DEBUG']) {
      \ORM::configure('logging', true);
      \ORM::configure('logger', function ($log_string, $query_time) {
        error_log('ORM: "' . $log_string . '" in ' . $query_time . "\n");
      });
    }
  }

  public function beginTransaction() {
    return \ORM::get_db()->beginTransaction();
  }

  public function commit() {
    return \ORM::get_db()->commit();
  }

  public function rollback() {
    return \ORM::get_db()->rollback();
  }

  public function factory($name) {
    return \Model::factory($name);
  }

  public function for_table($name) {
    return \ORM::for_table($name);
  }

  public function execute($query, $params= []) {
    return \ORM::raw_execute($query, $params);
  }

  public function get_last_statement() {
    return \ORM::get_last_statement();
  }

  public function escape($value) {
    return \ORM::get_pdo()->quote($value);
  }
}
