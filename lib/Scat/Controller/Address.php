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

      $address->easypost_id= $easypost_address->id;

      /* We copy back everything from EasyPost, which normalized it */
      $address->name= $easypost_address->name;
      $address->company= $easypost_address->company;
      $address->street1= $easypost_address->street1;
      $address->street2= $easypost_address->street2;
      $address->city= $easypost_address->city;
      $address->state= $easypost_address->state;
      $address->zip= $easypost_address->zip;
      $address->country= $easypost_address->country;
      $address->phone= $easypost_address->phone;
      $address->verified=
        $easypost_address->verifications->delivery->success ? '1' : '0';
      $address->residential=
        $easypost_address->residential ? '1' : '0';
      if ($easypost_address->verifications->delivery->details->longitude) {
        $address->latitude=
          $easypost_address->verifications->delivery->details->latitude;
        $address->longitude=
          $easypost_address->verifications->delivery->details->longitude;
      } else {
        $address->latitude= $address->longitude= null;
      }
      $address->timezone=
        $easypost_address->verifications->delivery->details->time_zone;
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
