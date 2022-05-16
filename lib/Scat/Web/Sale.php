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
    $sale= $this->data->factory('Cart')->where('uuid', $uuid)->find_one();

    if (!$sale || $sale->status != 'paid') {
      if (!$sale || $sale->status == 'sale') {
        // redirect to sale
      }
      if ($sale->status == '') {
        // other stuff
      }
    }

    return $this->view->render($response, 'sale/thanks.html', [
      'sale' => $sale,
    ]);
  }
}
