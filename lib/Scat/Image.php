<?php
namespace Scat;

class Image extends \Model implements \JsonSerializable {
  public function original() {
    return PUBLITIO_BASE . '/' . $this->uuid . '.' . $this->ext;
  }

  public function thumbnail() {
    return PUBLITIO_BASE .
           '/file/w_128,h_128,c_fit' .
           '/' . $this->uuid . '.jpg';
  }

  public function medium() {
    return PUBLITIO_BASE .
           '/file/w_384,h_384,c_fit' .
           '/' . $this->uuid . '.jpg';
  }

  public static function createFromUrl($url) {
    error_log("Creating image from URL '$url'");

    $publitio= new \Publitio\API(PUBLITIO_KEY, PUBLITIO_SECRET);

    $res= $publitio->call('/files/create', 'POST', [
      'file_url' => $url,
      'privacy' => 1,
      'option_ad' => 0,
      'tags' => $GLOBALS['DEBUG'] ? 'debug' : '',
    ]);

    if (!$res->success) {
      error_log(json_encode($res));
      throw new \Exception($res->error->message ? $res->error->message :
                           $res->message);
    }

    // Save the details
    $image= \Model::factory('Image')->create();
    $image->uuid= $res->public_id;
    $image->width= $res->width;
    $image->height= $res->height;
    $image->ext= $res->extension;
    $image->name= $res->title;
    $image->save();

    return $image;
  }

  public static function createFromPath($fn) {
    error_log("Creating image from path '$fn'");
    $file= \GuzzleHttp\PSR7\stream_for(fopen($fn, "r"));
    $name= basename($_FILES['src']['name']);
    return self::createFromFile($file, $name);
  }

  public static function createFromStream($file, $name) {
    $publitio= new \Publitio\API(PUBLITIO_KEY, PUBLITIO_SECRET);

    $res= $publitio->uploadFile($file, 'file', [
      'title' => $name,
      'public_id' => $uuid,
      'privacy' => 1,
      'option_ad' => 0,
      'tags' => $GLOBALS['DEBUG'] ? 'debug' : '',
    ]);

    if (!$res->success) {
      error_log(json_encode($res));
      throw new \Exception($res->error->message ? $res->error->message :
                           $res->message);
    }

    // Save the details
    $image= \Model::factory('Image')->create();
    $image->uuid= $res->public_id;
    $image->width= $res->width;
    $image->height= $res->height;
    $image->ext= $res->extension;
    $image->name= $res->title;
    $image->save();

    return $image;
  }

  public function jsonSerialize() {
    return $this->as_array();
  }
}
