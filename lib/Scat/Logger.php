<?php
namespace Scat;

class Logger extends \Monolog\Logger
{
  public function __construct(\Scat\Service\Config $config) {
    parent::__construct('Scat');

    $streamHandler= new \Monolog\Handler\StreamHandler('php://stderr');
    $this->pushHandler($streamHandler);
  }
}
