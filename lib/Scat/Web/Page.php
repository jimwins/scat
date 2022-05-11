<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Page {
  private $view, $data;

  public function __construct(View $view, \Scat\Service\Data $data)
  {
    $this->view= $view;
    $this->data= $data;
  }

  function page(Request $request, Response $response, $param,
                \Scat\Service\Catalog $catalog)
  {
    if (!$param) $param= '//';

    $content= $this->data->factory('Page')->where('slug', $param)->find_one();
    if (!$content) {
      // handle redirects
    }

    $template= ($GLOBALS['DEBUG'] && $request->getParam('edit')) ? 'edit.html' : 'index.html';

    return $this->view->render($response, $template, [
      'param' => $param,
      'content' => $content,
    ]);
  }

  function savePage(Request $request, Response $response, $param)
  {
    if (!$param) $param= '//';

    $content= $this->data->factory('Page')->where('slug', $param)->find_one();
    if (!$content) {
      $content= $this->data->factory('Page')->create();
      $content->slug= $param;
    }

    $content->title= $request->getParam('title');
    $content->content= $request->getParam('content');
    $content->description= $request->getParam('description');
    $content->format= $request->getParam('format');
    $content->save();

    $uri= preg_replace('!/~edit!', '', $request->getUri()->withQuery(""));

    return $response->withRedirect($uri);
  }
}
