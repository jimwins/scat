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
    View $view
  ) {
    $this->data= $data;
    $this->view= $view;
  }

  public function cart(Request $request, Response $response)
  {
    $cart= null;
    $person= [
      'friendly_name' => 'Jim Winstead',
    ];

    $template= 'cart/index.html';

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.cart+html') !== false) {
      $template= 'cart/popup.html';
    }

    return $this->view->render($response, $template, [
      'person' => $person,
      'cart' => $cart,
    ]);
  }
}
