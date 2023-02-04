<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Backroom {
  public function __construct(private View $view, private \Scat\Service\Data $data)
  {
  }

  function showAds(Request $request, Response $response) {
    $heros= $this->data->Factory('InternalAd')->where('active', 1)->where('tag', 'hero')->find_many();
    $basics= $this->data->Factory('InternalAd')->where('active', 1)->where('tag', 'basic')->find_many();

    return $this->view->render($response, "backroom/ads.html", [
      'heros' => $heros,
      'basics' => $basics,
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
