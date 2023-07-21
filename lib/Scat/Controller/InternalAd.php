<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class InternalAd {
  private $view, $data, $media;

  public function __construct(
    View $view,
    \Scat\Service\Data $data,
    \Scat\Service\Media $media
  ) {
    $this->view= $view;
    $this->data= $data;
    $this->media= $media;
  }

  public function home(Request $request, Response $response, View $view) {
    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $this->view->render($response, 'dialog/internal-ad-edit.html', [
        'ad' => [],
      ]);
    }

    $page= (int)$request->getParam('page');
    $page_size= 20;

    $ads= $this->data->factory('InternalAd')
      ->select('*')
      ->select_expr('COUNT(*) OVER()', 'records')
      ->order_by_desc('created_at')
      ->limit($page_size)->offset($page * $page_size);

    $q= $request->getParam('q');
    if ($q) {
      $ads= $ads->where_raw('MATCH (tag, headline, caption, button_label) AGAINST (? IN NATURAL LANGUAGE MODE)', [ $q ]);
    }

    $ads= $ads->find_many();

    return $view->render($response, 'ad/index.html', [
      'ads' => $ads,
      'q' => $q,
      'page' => $page,
      'page_size' => $page_size,
    ]);
  }

  public function show(Request $request, Response $response, View $view, $id) {
    $ad= $this->data->factory('InternalAd')->find_one($id);
    if (!$ad) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $this->view->render($response, 'dialog/internal-ad-edit.html', [
        'ad' => $ad,
      ]);
    }

    throw new \Slim\Exception\HttpNotFoundException($request);
  }

  public function update(Request $request, Response $response, $id= null) {
    if ($id) {
      $ad= $this->data->factory('InternalAd')->find_one($id);
      if (!$ad) {
        throw new \Slim\Exception\HttpNotFoundException($request);
      }
    } else {
      $ad= $this->data->factory('InternalAd')->create();
    }

    $dirty= false;

    foreach ($ad->getFields() as $field) {
      if ($field == 'id') continue; // don't allow changing id
      $value= $request->getParam($field);
      if ($value !== null) {
        $ad->$field= $value;
        $dirty= true;
      }
    }

    if ($ad->link_type == 'item') {
      $value= $request->getParam('item_id');
      if ($value !== null) {
        $ad->link_id= $value;
        $dirty= true;
      }
    }
    else if ($ad->link_type == 'product') {
      $value= $request->getParam('product_id');
      if ($value !== null) {
        $ad->link_id= $value;
        $dirty= true;
      }
    }

    $new= $ad->is_new();

    if ($dirty) {
      try {
        $ad->save();
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
                          ->withHeader('Location', '/ad/' . $ad->id);
    }

    return $response->withJson($ad);
  }

}
