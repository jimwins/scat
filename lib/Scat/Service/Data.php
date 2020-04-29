<?php
namespace Scat\Service;

class Data
{
  private $dsn, $options;

  public function __construct($config) {
    error_log("Configuring data\n");
    $this->dsn= $config['dsn'];
    $this->options= $config['options'];

    /* Configure Idiorm */
    \ORM::configure($this->dsn);
    foreach ($this->options as $option => $value) {
      \ORM::configure($option, $value);
    }

    /* Always want to throw exceptions for errors */
    \ORM::configure('error_mode', \PDO::ERRMODE_EXCEPTION);

    /* ... and Paris */
    \Model::$auto_prefix_models= '\\Scat\\Model\\';
    \Model::$short_table_names= true;

    // TODO use a logger service and always log at debug level?
    if ($GLOBALS['DEBUG'] || $GLOBALS['ORM_DEBUG']) {
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
}
