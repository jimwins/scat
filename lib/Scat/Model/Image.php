<?php
namespace Scat\Model;

class Image extends \Scat\Model {
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

  public function large_square() {
    return PUBLITIO_BASE .
           '/file/w_1024,h_1024,c_fill' .
           '/' . $this->uuid . '.jpg';
  }

  public function delete() {
    $publitio= new \Publitio\API(PUBLITIO_KEY, PUBLITIO_SECRET);

    $res= $publitio->call("/files/delete/" . $this->publitio_id,
                          'DELETE');

    if (!$res->success) {
      error_log(json_encode($res));
      throw new \Exception($res->error->message ? $res->error->message :
                           $res->message);
    }

    return parent::delete();
  }

  public function productsUsedBy() {
    return $this->has_many_through('Product');
  }
}
