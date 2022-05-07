<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Cart {
  private $view, $data;

  public function __construct(
    \Scat\Service\Data $data,
    \Scat\Service\Auth $auth,
    View $view
  ) {
    $this->data= $data;
    $this->auth= $auth;
    $this->view= $view;
  }

  public function cart(Request $request, Response $response)
  {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    return $this->view->render($response, 'cart/index.html', [
      'person' => $person,
      'cart' => $cart,
    ]);
  }
}
