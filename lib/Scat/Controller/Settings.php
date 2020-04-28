<?php
namespace Scat\Controller;

use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Settings {
  public function home(Response $response, View $view) {
    $settings= \Model::factory('Config')->order_by_asc('name')->find_many();
    return $view->render($response, 'settings/index.html', [
      'settings' => $settings,
    ]);
  }

  public function create(Request $request, Response $response) {
    $name= $request->getParam('name');
    // TODO validate

    $config= \Model::factory('Config')->create();
    $config->name= $name;
    $config->value= $request->getParam('value') ?: '';
    $config->save();

    return $response->withJson($config);
  }

  public function update(Request $request, Response $response, $id) {
    $config= \Model::factory('Config')->find_one($id);
    if (!$config)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $dirty= false;
    foreach (array_keys($config->as_array()) as $field) {
      if ($field == 'id') continue; // don't allow changing id
      $value= $request->getParam($field);
      if ($value !== null) {
        $config->set($field, $value);
        $dirty= true;
      }
    }

    if ($dirty) {
      try {
        $config->save();
      } catch (\PDOException $e) {
        if ($e->getCode() == '23000') {
          throw new \Scat\Exception\HttpConflictException($request);
        } else {
          throw $e;
        }
      }
    } else {
      return $response->withStatus(304);
    }

    return $response->withJson($config);
  }
}
