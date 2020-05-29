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
    $image->b2_file_id= $b2_file->getId();
    $image->publitio_id= $res->id;
    $image->width= $res->width;
    $image->height= $res->height;
    $image->ext= $res->extension;
    $image->name= $res->title;
    $image->save();

    return $image;
  }

  public function repairImage(\Scat\Model\Image $image) {
    $b2= $this->getB2Client();
    $bucket= $this->config->get('b2.bucketName');

    $publitio= new \Publitio\API(
      $this->config->get('publitio.key'),
      $this->config->get('publitio.secret')
    );

    $url= sprintf('%s/file/%s/%s',
                  $b2->getAuthorization()['downloadUrl'],
                  $bucket,
                  'i/o/' . $image->uuid . '.' . $image->ext);

    try {
      $res= $publitio->call("/files/delete/" . $image->publitio_id,
                            'DELETE');
    } catch (\Exception $e) {
      error_log("failed to delete from publit.io: ". $e->getMessage());
    }

    $res= $publitio->call('/files/create', 'POST', [
      'file_url' => $url,
      'public_id' => $image->uuid,
      'title' => $image->name,
      'privacy' => 1,
      'option_ad' => 0,
      'tags' => $GLOBALS['DEBUG'] ? 'debug' : '',
    ]);

    $image->publitio_id= $res->id;
    $image->width= $res->width;
    $image->height= $res->height;
    $image->ext= $res->extension;
    $image->save();

    return $image;
  }

  public function deleteImage(\Scat\Model\Image $image) {
    $b2= $this->getB2Client();
    $bucket= $this->config->get('b2.bucketName');

    $publitio= new \Publitio\API(
      $this->config->get('publitio.key'),
      $this->config->get('publitio.secret')
    );

    if ($image->publitio_id) {
      try {
        $res= $publitio->call("/files/delete/" . $image->publitio_id,
                              'DELETE');
      } catch (\Exception $e) {
        error_log("failed to delete from publit.io: ". $e->getMessage());
      }
    }

    if ($image->b2_file_id) {
      $data= [ 'FileId' => $image->b2_file_id ];
    } else {
      $data= [
        'BucketName' => $bucket,
        'FileName' => "i/o/{$image->uuid}.{$image->ext}",
      ];
    }

    try {
      $b2_file= $b2->deleteFile($data);
    } catch (\Exception $e) {
      error_log("failed to delete from B2: ". $e->getMessage());
    }

    // Purge references (going behind the scenes here!)
    $this->data->factory('ImageItem')
      ->where('image_id', $image->id)->delete_many();
    $this->data->factory('ImageProduct')
      ->where('image_id', $image->id)->delete_many();

    $image->delete();

    return [];
  }
}
