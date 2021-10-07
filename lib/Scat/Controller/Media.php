<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Media {
  private $data, $media;

  public function __construct(
    \Scat\Service\Data $data,
    \Scat\Service\Media $media
  ) {
    $this->data= $data;
    $this->media= $media;
  }

  public function home(Request $request, Response $response, View $view) {
    $page= (int)$request->getParam('page');
    $page_size= 20;

    $media= $this->data->factory('Image')
      ->select('*')
      ->select_expr('COUNT(*) OVER()', 'records')
      ->order_by_desc('created_at')
      ->limit($page_size)->offset($page * $page_size);

    $q= $request->getParam('q');
    if ($q) {
      $media= $media->where_raw('MATCH (name, alt_text, caption) AGAINST (? IN NATURAL LANGUAGE MODE)', [ $q ]);
    }

    $media= $media->find_many();

    $accept= $request->getHeaderLine('Accept');
    $xrequestedwith= $request->getHeaderLine('X-Requested-With');
    if (strpos($accept, 'application/json') !== false ||
        strtolower($xrequestedwith) === 'xmlhttprequest')
    {
      return $response->withJson([ 'media' => $media ]);
    }

    return $view->render($response, 'media/index.html', [
      'media' => $media,
      'q' => $q,
      'page' => $page,
      'page_size' => $page_size,
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
      $image= $this->media->createFromUrl($url);
    } else {
      foreach ($request->getUploadedFiles() as $file) {
        if ($file->getError() != UPLOAD_ERR_OK) {
          throw new \Scat\Exception\FileUploadException($file->getError());
        }
        $image= $this->media->createFromStream($file->getStream(),
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

  public function repair(Request $request, Response $response, $id) {
    $image= $this->data->factory('Image')->find_one($id);
    if (!$image) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    return $response->withJson($this->media->repairImage($image));
  }

  public function delete(Request $request, Response $response, $id) {
    $image= $this->data->factory('Image')->find_one($id);
    if (!$image) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    return $response->withJson($this->media->deleteImage($image));
  }

  public function addFromInstagram(Request $request, Response $response) {
    $type= $request->getParam('media_type');

    error_log("Media type is $type\n");
    file_put_contents('/tmp/insta.json', $request->getBody());

    $this->data->beginTransaction();

    if ($type == 'CAROUSEL_ALBUM') {
      $children= str_replace("'", '"', $request->getParam('children'));
      $media= json_decode($children, true);
    } else {
      $media= [ $request->getParams() ];
    }

    foreach ($media as $i) {
      $url= ($i['media_type']== 'IMAGE')
          ? $i['media_url']
          : $i['thumbnail_url'];

      $image= $this->media->createFromUrl($url);
      $image->caption= $i['caption'];
      $image->data= json_encode($i);
      $image->save();
    }

    $this->data->commit();

    return $response->withJson($image);
  }

  public function fix(Request $request, Response $response)
  {
    $b2= $this->media->getB2Client();
    $bucket= $this->media->getB2Bucket();

    $files= $b2->listFiles([
      'BucketName' => $bucket,
      'prefix' => 'i/o/' . ($GLOBALS['DEBUG'] ? 'DEBUG' : ''),
    ]);

    $out= [];

    foreach ($files as $file) {
      $pathinfo= pathinfo($file->getName());

      $uuid= $pathinfo['filename'];
      $ext= $pathinfo['extension'];

      if ($GLOBALS['DEBUG']) {
        $uuid= preg_replace('/^DEBUG/', '', $uuid);
      }

      $image= $this->data->factory('Image')->where('uuid', $uuid)->find_one();

      if (!$image) {
        $out[]= [
          'not_found' => $uuid,
        ];
      } elseif ($image->ext != $ext) {
        $out[]= [
          'ext_wrong' => $uuid,
          'old_ext' => $image->ext,
          'new_ext' => $ext,
        ];
        $image->ext= $ext;
        $image->save();
      } else {
        // do nothing
      }
    }

    return $response->withJson($out, 200, JSON_PRETTY_PRINT);
  }
}
