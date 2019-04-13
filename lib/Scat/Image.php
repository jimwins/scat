<?php
namespace Scat;

use Intervention\Image\ImageManagerStatic as ImageManager;

class Image extends \Model {
  public function thumbnail() {
    return '/i/th/' . $this->uuid . '.jpg';
  }

  public function medium() {
    return '/i/md/' . $this->uuid . '.jpg';
  }

  public static function createFromUrl($url) {
    $client= new \GuzzleHttp\Client();
    $res= $client->get($url);
    $file= $res->getBody();
    $name= basename(parse_url($url, PHP_URL_PATH));

    if (!$file) {
      throw new \Exception("Unable to load from $url");
    }

    return self::createFromFile($file, $name);
  }

  public static function createFromPath($fn) {
    $file= \GuzzleHttp\PSR7\stream_for(fopen($fn, "r"));
    $name= basename($_FILES['src']['name']);
    return self::createFromFile($file, $name);
  }

  public static function createFromFile($file, $name) {
    $ext= pathinfo($name, PATHINFO_EXTENSION);

    // Make sure we can read it as an image
    $img= ImageManager::make($file);

    // Could use real UUID() but this is shorter. Hardcoded '1' could be
    // replaced with a server-id to further avoid collisions
    $uuid= sprintf("%08x%02x%s", time(), 1, bin2hex(random_bytes(8)));

    // Upload the original
    $b2= new \ChrisWhite\B2\Client(B2_ACCOUNT_ID, B2_APPLICATION_KEY);

    $upload= $b2->upload([
      'BucketName' => B2_BUCKET,
      'FileName' => '/i/o/' . $uuid . '.' . $ext,
      'Body' => $file
    ]);

    // Save the details
    $image= \Model::factory('Image')->create();
    $image->uuid= $uuid;
    $image->width= $img->width();
    $image->height= $img->height();
    $image->ext= $ext;
    $image->name= $name;
    $image->save();

    // Add a background color if it's a PNG
    if ($img->mime() == 'image/png') {
      $new= ImageManager::canvas($img->width(), $img->height(), '#ffffff')
              ->insert($img);
      $img->destroy(); // free up that memory!
      $img= $new;
    }

    // Generate the standard size image (max 768px)
    $img->resize(768, null, function ($constraint) {
        $constraint->aspectRatio();
        $constraint->upsize();
    });

    $upload= $b2->upload([
      'BucketName' => B2_BUCKET,
      'FileName' => '/i/st/' . $uuid . '.jpg',
      'Body' => $img->stream('jpg', 75)
    ]);

    // Generate the medium size image (max 384px)
    $img->resize(384, null, function ($constraint) {
        $constraint->aspectRatio();
        $constraint->upsize();
    });

    $upload= $b2->upload([
      'BucketName' => B2_BUCKET,
      'FileName' => '/i/md/' . $uuid . '.jpg',
      'Body' => $img->stream('jpg', 75)
    ]);

    // Generate the thumbnail (max 128px)
    $img->resize(128, null, function ($constraint) {
        $constraint->aspectRatio();
        $constraint->upsize();
    });

    $upload= $b2->upload([
      'BucketName' => B2_BUCKET,
      'FileName' => '/i/th/' . $uuid . '.jpg',
      'Body' => $img->stream('jpg', 75)
    ]);

    return $image;
  }
}
