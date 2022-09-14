<?php
namespace Scat;

class Logger extends \Monolog\Logger
{
  public function __construct(\Scat\Service\Config $config) {
    parent::__construct('Scat');

    $streamHandler= new \Monolog\Handler\StreamHandler('php://stderr');
    $this->pushHandler($streamHandler);

    $sentry_dsn= $config->get('sentry.dsn');
    if ($sentry_dsn) {
      \Sentry\init([ 'dsn' => $sentry_dsn ]);
      $sentry= new \Sentry\Monolog\Handler(\Sentry\SentrySdk::getCurrentHub());
      $this->pushHandler($sentry);
    }
  }
}
