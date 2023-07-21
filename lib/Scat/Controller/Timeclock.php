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

  function getPunch(Request $request, Response $response, View $view, $id) {
    $punch= $this->data->factory('Timeclock')->find_one($id);
    if (!$punch) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      $people= $this->data->factory('Person')->where('role', 'employee')->find_many();
      return $view->render($response, 'dialog/punch.html', [
        'people' => $people,
        'punch' => $punch,
      ]);
    }

    return $response->withJson($punch);
  }

  function updatePunch(Request $request, Response $response, $id) {
    $punch= $this->data->factory('Timeclock')->find_one($id);
    if (!$punch) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $before= clone $punch;

    $dirty= false;

    foreach ($punch->getFields() as $field) {
      if ($field == 'id') continue; // don't allow changing id
      $value= $request->getParam($field);
      if ($value !== null && $value !== $punch->get($field)) {
        if ($value === '') $value= null;
        $punch->set($field, $value);
        $dirty= true;
      }
    }

    if ($dirty) {
      $this->data->beginTransaction();

      /* We save a history of changes */

      $audit= $this->data->factory('TimeclockAudit')->create();
      $audit->timeclock_id= $punch->id;
      $audit->before_start= $before->start;
      $audit->before_end= $before->end;
      $audit->after_start= $punch->start;
      $audit->after_end= $punch->end;
      $audit->save();

      $punch->save();

      $this->data->commit();
    }


    return $response->withJson($punch);
  }
}
