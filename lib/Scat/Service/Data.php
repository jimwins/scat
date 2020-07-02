<?php
namespace Scat\Service;

class Data
{
  private $dsn, $options;

  public function __construct($config) {
    $this->dsn= $config['dsn'];
    $this->options= $config['options'];

    /* Configure Titi */
    \Titi\ORM::configure($this->dsn);
    foreach ($this->options as $option => $value) {
      \Titi\ORM::configure($option, $value);
    }
    \Titi\ORM::configure('driver_options', [
      \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
    ]);

    /* Always want to throw exceptions for errors */
    \Titi\ORM::configure('error_mode', \PDO::ERRMODE_EXCEPTION);

    /* ... and Paris */
    \Titi\Model::$auto_prefix_models= '\\Scat\\Model\\';
    \Titi\Model::$short_table_names= true;

    // TODO use a logger service and always log at debug level?
    if ($GLOBALS['DEBUG'] || $GLOBALS['ORM_DEBUG']) {
      \Titi\ORM::configure('logging', true);
      \Titi\ORM::configure('logger', function ($log_string, $query_time) {
        error_log('ORM: "' . $log_string . '" in ' . $query_time . "\n");
      });
    }
  }

  public function beginTransaction() {
    return \Titi\ORM::get_db()->beginTransaction();
  }

  public function commit() {
    return \Titi\ORM::get_db()->commit();
  }

  public function rollback() {
    return \Titi\ORM::get_db()->rollback();
  }

  public function factory($name) {
    return \Titi\Model::factory($name);
  }

  public function configure($name, $value) {
    return \Titi\ORM::configure($name, $value);
  }

  public function for_table($name) {
    return \Titi\ORM::for_table($name);
  }

  public function execute($query, $params= []) {
    return \Titi\ORM::raw_execute($query, $params);
  }

  public function get_last_statement() {
    return \Titi\ORM::get_last_statement();
  }

  public function escape($value) {
    return \Titi\ORM::get_db()->quote($value);
  }
}
