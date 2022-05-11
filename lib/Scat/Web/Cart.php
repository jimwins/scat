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

  public function addItem(Request $request, Response $response)
  {
    $cart= $request->getAttribute('cart');

    if (!$cart) {
      // TODO create new cart
      // attach it to person, if logged in
    }

    $item_code= trim($request->getParam('item'));
    $quantity= max((int)$request->getParam('quantity'), 1);

    if ($cart->closed()) {
      throw new \Exception("Cart already completed, start another one.");
    }

    // get item details
    $item= 0;

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($cart);
    }

    return $response->withRedir('/cart');
  }
}
