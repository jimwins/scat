<?php
namespace Scat\Controller;

use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Settings {
  private $data, $config;

  public function __construct(
    \Scat\Service\Data $data,
    \Scat\Service\Config $config
  ) {
    $this->data= $data;
    $this->config= $config;
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

  public function address(Request $request, Response $response, View $view)
  {
    $address=
      $this->data->factory('Address')->find_one(1);
    return $view->render($response, 'settings/address.html', [
      'address' => $address,
    ]);
  }

  public function wordform(Request $request, Response $response, View $view,
                          $wordform_id= null)
  {
    if ($wordform_id) {
      $wordform= $this->data->factory('Wordform')->find_one($wordform_id);
    }

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

  public function connectGoogle(Request $request, Response $response,
                                View $view)
  {
    $client_id= $this->config->get('google.client_id');
    $client_secret= $this->config->get('google.client_secret');
    $token= $this->config->get('google.oauth_access_token');
    if ($token) {
      $token= json_decode($token, true);
    }

    if ($request->getParam('reauthorize') && $token) {
      $this->config->forget('google.oauth_access_token');
      $token= null;
    }

    if ($request->getParam('client_id')) {
      $client_id= $request->getParam('client_id');
      $this->config->set('google.client_id', $client_id);
    }

    if ($request->getParam('client_secret')) {
      $client_secret= $request->getParam('client_secret');
      $this->config->set('google.client_secret', $client_secret, 'password');
    }

    if ($client_id && $client_secret) {
      $client= new \Google\Client();
      $client->setApplicationName('Scat POS');
      $client->setClientId($client_id);
      $client->setClientSecret($client_secret);
      $client->setRedirectUri($request->getUri()->withQuery(""));
      $client->setAccessType('offline');
      $client->setIncludeGrantedScopes(true);
      $client->setScopes('https://www.googleapis.com/auth/content');

      if ($token) {
        $client->setAccessToken($token);
      } elseif (($code= $request->getParam('code'))) {
        $token= $client->authenticate($code);
        if (array_key_exists('error', $token)) {
          throw new \Exception($token['error']);
        }
        $this->config->set('google.oauth_access_token', json_encode($token), 'password');
      } else {
        return $response->withRedirect($client->createAuthUrl());
      }
    }

    return $view->render($response, 'settings/google.html', [
      'client_id' => $client_id,
      'token' => $token,
    ]);
  }
}
