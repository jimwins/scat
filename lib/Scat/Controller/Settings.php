<?php
namespace Scat\Controller;

use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Settings {
  public function __construct(
    private \Scat\Service\Data $data,
    private \Scat\Service\Config $config
  ) {
  }

  public function home(Response $response, View $view) {
    $settings= $this->data->factory('Config')->order_by_asc('name')->find_many();
    $address= $this->data->factory('Address')->find_one(1);
    return $view->render($response, 'settings/index.html', [
      'settings' => $settings,
      'address' => $address,
    ]);
  }

  public function advanced(Response $response, View $view) {
    $settings= $this->data->factory('Config')->order_by_asc('name')->find_many();
    return $view->render($response, 'settings/advanced.html', [
      'settings' => $settings,
    ]);
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

  public function updateByName(Request $request, Response $response) {
    $name= $request->getParam('name');
    $value= $request->getParam('value') ?: '';
    $type= $request->getParam('type');

    $config= $this->config->set($name, $value, $type);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($config);
    }

    return $response->withRedirect('/settings');
  }

  public function printing(
    Request $request, Response $response,
    View $view,
    \Scat\Service\Printer $print
  ) {
    $printers= $print->getPrinters();
    return $view->render($response, 'settings/printing.html', [
      'printers' => $printers,
    ]);
  }

  public function updatePrinting(Request $request, Response $response) {
    if ($request->getParam('server')) {
      $host= $request->getParam('host');
      $user= $request->getParam('user');
      $pass= $request->getParam('pass');

      $this->config->set('cups.host', $host);
      $this->config->set('cups.user', $user);
      $this->config->set('cups.pass', $pass);
    } else {
      $types= [ 'label', 'letter', 'receipt', 'shipping-label' ];

      foreach ($types as $type) {
        $this->config->set('printer.' . $type, $request->getParam('printer_' . $type) ?? '');
      }
    }

    return $response->withRedirect('/settings/printing');
  }

  public function message(Request $request, Response $response, View $view,
                          $message_id= null)
  {
    $message= $message_id ? $this->data->factory('CannedMessage')->find_one($message_id) : null;

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

  public function shipping(Request $request, Response $response, View $view)
  {
    $settings= $this->data->factory('Config')->order_by_asc('name')->find_many();
    $address= $this->data->factory('Address')->find_one(1);
    return $view->render($response, 'settings/shipping.html', [
      'settings' => $settings,
      'address' => $address,
    ]);
  }

  public function wordform(Request $request, Response $response, View $view,
                          $wordform_id= null)
  {
    $wordform= $wordform_id ? $this->data->factory('Wordform')->find_one($wordform_id) : null;

    if ($wordform_id && !$wordform)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($wordform);
    }
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $view->render($response, 'dialog/wordform.html', [
        'wordform' => $wordform
      ]);
    }

    $wordforms=
      $this->data->factory('Wordform')
           ->order_by_asc('source')->find_many();
    return $view->render($response, 'settings/wordforms.html', [
      'wordforms' => $wordforms,
    ]);
  }

  public function wordformUpdate(Request $request, Response $response,
                                $wordform_id= null)
  {
    if ($wordform_id) {
      $wordform= $this->data->factory('Wordform')->find_one($wordform_id);
    } else {
      $wordform= $this->data->factory('Wordform')->create();
    }

    if ($wordform_id && !$wordform)
      throw new \Slim\Exception\HttpNotFoundException($request);

    foreach ($wordform->getFields() as $field) {
      $value= $request->getParam($field);
      if (isset($value)) {
        $wordform->set($field, $value);
      }
    }

    $wordform->save();

    return $response->withJson($wordform);
  }
}
