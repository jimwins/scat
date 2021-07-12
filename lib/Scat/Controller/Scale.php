<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Scale {
  private $config;

  public function __construct(\Scat\Service\Config $config) {
    $this->config= $config;
  }

  function home(Request $request, Response $response) {
    $client= new \GuzzleHttp\Client();

    $path= $this->config->get('scale.url');

    $res= $client->get($path);
    $body= (string)$res->getBody();

    $response->getBody()->write($body);

    return $response->withHeader('Content-type', 'text/plain');
  }
}
