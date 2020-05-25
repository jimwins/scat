<?php
namespace Scat\Service;

class Media
{
  private $config, $data;

  public function __construct(Config $config, Data $data) {
    $this->config= $config;
    $this->data= $data;
  }

  protected function getB2Client() {
    $id= $this->config->get('b2.keyID');
    $key= $this->config->get('b2.applicationKey');
    error_log("keyID = $id, key = $key\n");
    return new \ChrisWhite\B2\Client($id, $key);
  }

  public function createFromUrl($url) {
    error_log("Creating image from URL '$url'\n");

    $client= new \GuzzleHttp\Client();
    $response= $client->get($url);

    $file= $response->getBody();
    $name= basename(parse_url($url, PHP_URL_PATH));

    return $this->createFromStream($file, $name);
  }

  public function createFromStream($file, $name) {
    error_log("Creating image from stream '$name'\n");
    $b2= $this->getB2Client();
    $bucket= $this->config->get('b2.bucketName');

    $uuid= sprintf("%08x%02x%s", time(), 0, bin2hex(random_bytes(8)));

    $ext= pathinfo($name, PATHINFO_EXTENSION);

    $b2_file= $b2->upload([
      'BucketName' => $bucket,
      'FileName' => "i/o/$uuid.$ext",
      'Body' => $file,
    ]);

    $publitio= new \Publitio\API(
      $this->config->get('publitio.key'),
      $this->config->get('publitio.secret')
    );

    $url= sprintf('%s/file/%s/%s',
                  $b2->getAuthorization()['downloadUrl'],
                  $bucket,
                  $b2_file->getName());

    $res= $publitio->call('/files/create', 'POST', [
      'file_url' => $url,
      'public_id' => $uuid,
      'title' => $name,
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
