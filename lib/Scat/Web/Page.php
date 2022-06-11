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
      $item= $catalog->getItemByCode($param);
      if ($item && $item->active) {
        $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
        $routeParser= $routeContext->getRouteParser();
        return $response->withRedirect(
          $routeParser->urlFor(
            'catalog',
            $item->url_params(),
          )
        );
      }
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    if ($content->format == 'redirect') {
      return $response->withRedirect($content->content);
    }

    $template= ($GLOBALS['DEBUG'] && $request->getParam('edit')) ? 'edit.html' : 'index.html';

    return $this->view->render($response, $template, [
      'param' => $param,
      'request' => $request->getParams(),
      'content' => $content,
    ]);
  }

  function savePage(Request $request, Response $response, $param= null)
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

  function robotsTxt(Request $request, Response $response) {
    return $this->view->render($response, 'robots.txt')
                ->withHeader('Content-type', 'text/plain;charset=UTF-8');
  }

  function sitemap(Request $request, Response $response) {
    return $this->view->render($response, 'sitemap.xml')
                ->withHeader('Content-type', 'text/xml;charset=UTF-8');
  }
}
