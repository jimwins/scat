<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Page {
  private $view;

  public function __construct(View $view) {
    $this->view= $view;
  }

  function page(Request $request, Response $response, $param,
                \Scat\Service\Catalog $catalog)
  {
    return $this->view->render($response, 'index.html', [
      'param' => $param,
      'departments' => $catalog->getDepartments(),
    ]);
  }
}

