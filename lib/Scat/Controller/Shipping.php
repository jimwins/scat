<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Respect\Validation\Validator as v;

class Shipping {
  private $shipping;

  public function __construct(\Scat\Service\Shipping $shipping) {
    $this->shipping= $shipping;
  }

  function register(Request $request, Response $response) {
    return $response->withJson($this->shipping->registerWebhook());
  }

}
