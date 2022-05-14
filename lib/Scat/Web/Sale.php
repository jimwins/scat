<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Sale {
  private $view, $catalog, $data;

  public function __construct(
    \Scat\Service\Data $data,
    \Scat\Service\Catalog $catalog,
    \Scat\Service\Config $config,
    \Scat\Service\Auth $auth,
    View $view
  ) {
    $this->data= $data;
    $this->auth= $auth;
    $this->catalog= $catalog;
    $this->config= $config;
    $this->view= $view;
  }

  public function thanks(Request $request, Response $response, $uuid) {
    $cart= $this->data->factory('Cart')->where('uuid', $uuid)->find_one();

    if (!$cart || $cart->status != 'paid') {
      if (!$cart || $cart->status == 'cart') {
        // redirect to cart
      }
      if ($cart->status == '') {
        // other stuff
      }
    }

    return $response->withJson($cart);
  }
}
