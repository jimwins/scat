<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Psr\Http\Message\RequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Respect\Validation\Validator as v;

class SMS {
  protected $container;

  public function __construct(ContainerInterface $container) {
    $this->container= $container;
  }

  function send(Request $req, Response $res, array $args) {
    $data= $this->container->phone->sendSMS($req->getParam('to'),
                                            $req->getParam('text'));
    return $res->withJson($data);
  }

  function register(Request $req, Response $res, array $args) {
    $data= $this->container->phone->registerWebhook();
    return $res->withJson($data);
  }

  function receive(Request $req, Response $res, array $args) {
  }
}
