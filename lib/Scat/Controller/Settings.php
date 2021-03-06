<?php
namespace Scat\Controller;

use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Settings {
  private $data;

  public function __construct(\Scat\Service\Data $data) {
    $this->data= $data;
  }

  public function home(Response $response, View $view) {
    $settings= $this->data->factory('Config')->order_by_asc('name')->find_many();
    return $view->render($response, 'settings/index.html', [
      'settings' => $settings,
    ]);
  }

  public function create(Request $request, Response $response) {
    $name= $request->getParam('name');
    // TODO validate

    $config= $this->data->factory('Config')->create();
    $config->name= $name;
    $config->value= $request->getParam('value') ?: '';
    $config->save();

    return $response->withJson($config);
  }

  public function update(Request $request, Response $response, $id) {
    $config= $this->data->factory('Config')->find_one($id);
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

  public function listPrinters(Request $request, Response $response,
                                \Scat\Service\Printer $print)
  {
    return $response->withJson($print->getPrinters());
  }

  public function message(Request $request, Response $response, View $view,
                          $message_id= null)
  {
    if ($message_id) {
      $message= $this->data->factory('CannedMessage')->find_one($message_id);
    }

    if ($message_id && !$message)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($message);
    }
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $view->render($response, 'dialog/message.html', [
        'message' => $message
      ]);
    }

    $messages=
      $this->data->factory('CannedMessage')
           ->order_by_asc('slug')->find_many();
    return $view->render($response, 'settings/messages.html', [
      'messages' => $messages,
    ]);
  }

  public function messageUpdate(Request $request, Response $response,
                                $message_id= null)
  {
    if ($message_id) {
      $message= $this->data->factory('CannedMessage')->find_one($message_id);
    } else {
      $message= $this->data->factory('CannedMessage')->create();
    }

    if ($message_id && !$message)
      throw new \Slim\Exception\HttpNotFoundException($request);

    foreach ($message->getFields() as $field) {
      $value= $request->getParam($field);
      if (isset($value)) {
        $message->set($field, $value);
      }
    }

    $message->save();

    return $response->withJson($message);
  }
}
