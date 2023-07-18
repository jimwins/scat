<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Address {
  private $view, $data;

  public function __construct(
    View $view,
    \Scat\Service\Data $data,
    \Scat\Service\Shipping $shipping
  ) {
    $this->view= $view;
    $this->data= $data;
    $this->shipping= $shipping;
  }

  public function show(Request $request, Response $response, View $view, $id) {
    $address= $this->data->factory('Address')->find_one($id);
    if (!$address) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $this->view->render($response, 'dialog/address.html', [
        'address' => $address,
      ]);
    }

    return $response->withJson($address);
  }

  public function update(Request $request, Response $response, $id= null) {
    if ($id) {
      $address= $this->data->factory('Address')->find_one($id);
      if (!$address) {
        throw new \Slim\Exception\HttpNotFoundException($request);
      }
    } else {
      $address= $this->data->factory('Address')->create();
    }

    $dirty= false;

    foreach ($address->getFields() as $field) {
      if ($field == 'id') continue; // don't allow changing id
      $value= $request->getParam($field);
      if ($value !== null && $value !== $address->get($field)) {
        $address->set($field, $value);
        $dirty= true;
      }
    }

    $new= $address->is_new();

    /* If it is dirty update the EasyPost address */
    if ($dirty) {
      $details= $address->as_array();
      $details['verify']= [ 'delivery' ];

      $easypost_address= $this->shipping->createAddress($details);
      $address->setFromEasypostAddress($easypost_address);
    }

    $address->save();

    if ($dirty) {
      try {
        $address->save();
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

    if ($new) {
      $response= $response->withStatus(201)
                          ->withHeader('Location', '/address/' . $address->id);
    }

    return $response->withJson($address);
  }

}
