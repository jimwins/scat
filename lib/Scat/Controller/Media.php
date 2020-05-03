<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Media {
  private $data;

  public function __construct(\Scat\Service\Data $data) {
    $this->data= $data;
  }

  public function home(Request $request, Response $response, View $view) {
    $page= (int)$request->getParam('page');
    $page_size= 20;
    $media= $this->data->factory('Image')
      ->order_by_desc('created_at')
      ->limit($page_size)->offset($page * $page_size)
      ->find_many();
    $total= $this->data->factory('Image')->count();

    return $view->render($response, 'media/index.html', [
      'media' => $media,
      'page' => $page,
      'page_size' => $page_size,
      'total' => $total,
    ]);
  }

  public function show(Request $request, Response $response, View $view, $id) {
    $image= $this->data->factory('Image')->find_one($id);
    if (!$image) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }
    return $view->render($response, 'media/single.html', [
      'image' => $image,
    ]);
  }

  public function create(Request $request, Response $response) {
    $url= $request->getParam('url');
    if ($url) {
      $image= \Scat\Model\Image::createFromUrl($url);
    } else {
      foreach ($request->getUploadedFiles() as $file) {
        $image= \Scat\Model\Image::createFromStream($file->getStream(),
                                              $file->getClientFilename());
      }
    }

    return $response->withJson($image);
  }

  public function update(Request $request, Response $response, $id) {
    $image= $this->data->factory('Image')->find_one($id);
    if (!$image) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    foreach ($image->getFields() as $field) {
      if ($field == 'id') continue; // don't allow changing id
      $value= $request->getParam($field);
      if ($value !== null) {
        $image->$field= $value;
        $dirty= true;
      }
    }

    $new= $image->is_new();

    if ($dirty) {
      try {
        $image->save();
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
                          ->withHeader('Location', '/image/' . $image->id);
    }

    return $response->withJson($image);
  }

  public function delete(Request $request, Response $response, $id) {
    $image= $this->data->factory('Image')->find_one($id);
    if (!$image) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }
    $image->delete();
    return $response->withJson([ 'message' => 'Deleted.' ]);
  }

  public function addFromInstagram(Request $request, Response $response) {
    $type= $request->getParam('media_type');

    error_log($request->getBody() . "\n");

    $this->data->beginTransaction();

    $url= ($type == 'IMAGE') ? $request->getParam('media_url') :
                               $request->getParam('thumbnail_url');

    $image= \Scat\Model\Image::createFromUrl($url);
    $image->caption= $request->getParam('caption');
    $image->data= $request->getBody();
    $image->save();

    $this->data->commit();

    return $response->withJson($image);
  }
}
