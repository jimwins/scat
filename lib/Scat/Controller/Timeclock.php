<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Timeclock {
  private $data;

  public function __construct(\Scat\Service\Data $data) {
    $this->data= $data;
  }

  function home(Request $request, Response $response, View $view) {
    $people= $this->data->factory('Person')
      ->select('*')
      ->where('role', 'employee')
      ->order_by_asc('name')
      ->find_many();

    if (($block= $request->getParam('block'))) {
      $out= $view->fetchBlock('clock/index.html', $block, [
        'people' => $people,
      ]);
      $response->getBody()->write($out);
      return $response;
    } else {
      return $view->render($response, 'clock/index.html', [
        'people' => $people,
      ]);
    }
  }

  function punch(Request $request, Response $response) {
    $id= $request->getParam('id');
    $person= $this->data->factory('Person')->find_one($id);
    return $response->withJson($person->punch());
  }
}
