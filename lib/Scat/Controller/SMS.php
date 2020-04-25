<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Respect\Validation\Validator as v;

class SMS {
  protected $container;

  public function __construct(ContainerInterface $container) {
    $this->container= $container;
  }

  function send(Request $request, Response $response) {
    $data= $this->container->get('phone')->sendSMS($request->getParam('to'),
                                            $request->getParam('text'));
    return $response->withJson($data);
  }

  function register(Request $request, Response $response) {
    $data= $this->container->get('phone')->registerWebhook();
    return $response->withJson($data);
  }

  function receive(Request $request, Response $response) {
  }
}
