<?php
namespace Scat\Service;

class Media
{
  private $config, $data;

  public function __construct(Config $config, Data $data) {
    $this->config= $config;
    $this->data= $data;
  }

  public function createFromUrl($url) {
    error_log("Creating image from URL '$url'");

    $publitio= new \Publitio\API(
      $this->config->get('publitio.key'),
      $this->config->get('publitio.secret')
    );

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
    $image= $this->data->factory('Image')->create();
    $image->uuid= $res->public_id;
    $image->width= $res->width;
    $image->height= $res->height;
    $image->ext= $res->extension;
    $image->name= $res->title;
    $image->save();

    return $image;
  }

  public function createFromStream($file, $name) {
    $publitio= new \Publitio\API(
      $this->config->get('publitio.key'),
      $this->config->get('publitio.secret')
    );

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
    $image= $this->data->factory('Image')->create();
    $image->uuid= $res->public_id;
    $image->publitio_id= $res->id;
    $image->width= $res->width;
    $image->height= $res->height;
    $image->ext= $res->extension;
    $image->name= $res->title;
    $image->save();

    return $image;
  }
}
