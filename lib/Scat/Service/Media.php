<?php
namespace Scat\Service;

class Media
{
  private $config, $data, $ordure;

  public function __construct(Config $config, Data $data, Ordure $ordure) {
    $this->config= $config;
    $this->data= $data;
    $this->ordure= $ordure;
  }

  public function findById($id) {
    return $this->data->factory('Image')->find_one($id);
  }

  public function getB2Client() {
    $id= $this->config->get('b2.keyID');
    $key= $this->config->get('b2.applicationKey');
    return new \ChrisWhite\B2\Client($id, $key);
  }

  public function getB2Bucket() {
    return $this->config->get('b2.bucketName');
  }

  public function createFromUrl($url) {
    $upload= $this->ordure->grabImage($url);
    $base= $this->config->get('gumlet.base_url');

    $url= $base .
           $upload->uuid . '.' . $upload->ext .
           '?fm=json';
    $body= file_get_contents($url);
    $details= json_decode($body);

    // Save the details
    $image= $this->data->factory('Image')->create();
    $image->uuid= $upload->uuid;
    $image->b2_file_id= $upload->id;
    $image->width= $details->width;
    $image->height= $details->height;
    $image->ext= $upload->ext;
    $image->name= $upload->name;
    $image->save();

    return $image;
  }

  public function createFromStream($file, $name) {
    error_log("Creating image from stream '$name'\n");
    $b2= $this->getB2Client();
    $bucket= $this->config->get('b2.bucketName');

    $uuid= sprintf("%08x%02x%s", time(), 0, bin2hex(random_bytes(8)));

    // No extension? Probably a JPEG
    $ext= pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg';

    $b2_file= $b2->upload([
      'BucketName' => $bucket,
      'FileName' => "i/o/$uuid.$ext",
      'Body' => $file,
    ]);

    $base= $this->config->get('gumlet.base_url');
    $url= $base .
           $uuid . '.' . $ext .
           '?fm=json';
    $body= file_get_contents($url);
    $details= json_decode($body);

    // Save the details
    $image= $this->data->factory('Image')->create();
    $image->uuid= $uuid;
    $image->b2_file_id= $b2_file->getId();
    $image->width= $details->width;
    $image->height= $details->height;
    $image->ext= $ext;
    $image->name= $name;
    $image->save();

    return $image;
  }

  public function repairImage(\Scat\Model\Image $image) {
    $b2= $this->getB2Client();
    $bucket= $this->config->get('b2.bucketName');

    $url= $image->original() . '?fm=jpg';
    $upload= $this->ordure->grabImage($url, [ 'ext' => 'jpg' ]);

    $image->uuid= $upload->uuid;
    $image->b2_file_id= $upload->id;
    $image->ext= $upload->ext;
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
