<?php
namespace Scat;

class Image extends \Model implements \JsonSerializable {
  public function original() {
    return '/i/o/' . $this->uuid . '.' . $this->ext;
  }

  public function thumbnail() {
    return '/i/th/' . $this->uuid . '.jpg';
  }

  public function medium() {
    return '/i/md/' . $this->uuid . '.jpg';
  }

  public function regenerate($original= null) {
    $client= new \GuzzleHttp\Client();

    $b2= new \ChrisWhite\B2\Client(B2_ACCOUNT_ID, B2_APPLICATION_KEY);

    $short= new \Scat\ShortPixel(SHORTPIXEL_KEY, [
      'convertto' => 'jpg',
      'wait' => 30
    ]);

    // XXX must be a better way to do this, but this works for now
    if (!$original) {
      $original= 'https:' . ORDURE_STATIC . $this->original();
      error_log("original: $original");
    }

    // Generate the standard size image (max 768px)
    $res= $short->reduceUrl($original, [
      'refresh' => 1,
      'resize' => 3,
      'resize_width' => 768,
      'resize_height' => 768,
    ]);

    $res= $client->get($res->LossyURL);
    $file= $res->getBody();

    $upload= $b2->upload([
      'BucketName' => B2_BUCKET,
      'FileName' => '/i/st/' . $this->uuid . '.jpg',
      'Body' => $file
    ]);

    // Generate the medium size image (max 384px)
    $res= $short->reduceUrl($original, [
      'refresh' => 1,
      'resize' => 3,
      'resize_width' => 384,
      'resize_height' => 384,
    ]);

    $res= $client->get($res->LossyURL);
    $file= $res->getBody();

    $upload= $b2->upload([
      'BucketName' => B2_BUCKET,
      'FileName' => '/i/md/' . $this->uuid . '.jpg',
      'Body' => $file
    ]);

    // Generate the thumbnail (max 128px)
    $res= $short->reduceUrl($original, [
      'refresh' => 1,
      'resize' => 3,
      'resize_width' => 128,
      'resize_height' => 128,
    ]);

    $res= $client->get($res->LossyURL);
    $file= $res->getBody();

    $upload= $b2->upload([
      'BucketName' => B2_BUCKET,
      'FileName' => '/i/th/' . $this->uuid . '.jpg',
      'Body' => $file
    ]);

    return $this;
  }

  public static function createFromUrl($url) {
    error_log("Creating image from URL '$url'");

    // Could use real UUID() but this is shorter. Hardcoded '1' could be
    // replaced with a server-id to further avoid collisions
    $uuid= sprintf("%08x%02x%s", time(), 1, bin2hex(random_bytes(8)));

    // Make debug uploads easier to find
    if ($GLOBALS['DEBUG']) {
      $uuid= "DEBUG" . substr($uuid, 5);
    }

    $publitio= new \Publitio\API(PUBLITIO_KEY, PUBLITIO_SECRET);

    $res= $publitio->call('/files/create', 'POST', [
      'file_url' => $url,
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
    $image->uuid= $uuid;
    $image->width= $res->width;
    $image->height= $res->height;
    $image->ext= $res->extension;
    $image->name= $res->title;
    $image->save();

    // XXX old method
    $name= basename(parse_url($url, PHP_URL_PATH));
    $ext= $res->extension;

    $short= new \Scat\ShortPixel(SHORTPIXEL_KEY, [
      'convertto' => 'jpg',
      'wait' => 30
    ]);

    // Just "glossy" compression on the original
    $res= $short->reduceUrl($url, [ 'lossy' => 2 ]);

    if ($res->LossyURL && $res->Status->Code == 2) {
      $original= $res->OriginalURL;
      $lossy= $res->LossyURL;
    }

    // Upload the glossy as the original
    $b2= new \ChrisWhite\B2\Client(B2_ACCOUNT_ID, B2_APPLICATION_KEY);

    $client= new \GuzzleHttp\Client();
    $res= $client->get($lossy);
    $orig_file= $res->getBody();

    $upload= $b2->upload([
      'BucketName' => B2_BUCKET,
      'FileName' => '/i/o/' . $uuid . '.' . $ext,
      'Body' => $orig_file
    ]);

    return $image->regenerate($original);
  }

  public static function createFromPath($fn) {
    error_log("Creating image from path '$fn'");
    $file= \GuzzleHttp\PSR7\stream_for(fopen($fn, "r"));
    $name= basename($_FILES['src']['name']);
    return self::createFromFile($file, $name);
  }

  public static function createFromStream($file, $name) {
    // Could use real UUID() but this is shorter. Hardcoded '1' could be
    // replaced with a server-id to further avoid collisions
    $uuid= sprintf("%08x%02x%s", time(), 1, bin2hex(random_bytes(8)));

    // Make debug uploads easier to find
    if ($GLOBALS['DEBUG']) {
      $uuid= "DEBUG" . substr($uuid, 5);
    }

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
    $image->uuid= $uuid;
    $image->width= $res->width;
    $image->height= $res->height;
    $image->ext= $res->extension;
    $image->name= $res->title;
    $image->save();

    $ext= pathinfo($name, PATHINFO_EXTENSION);

    $short= new \Scat\ShortPixel(SHORTPIXEL_KEY, [
      'convertto' => 'jpg',
      'wait' => 30
    ]);

    // Just "glossy" compression on the original
    $res= $short->reduceStream($file, $name, [ 'lossy' => 2 ]);

    if ($res->LossyURL && $res->Status->Code == 2) {
      $original= $res->OriginalURL;
      $lossy= $res->LossyURL;
    }

    // Upload the glossy as the original
    $b2= new \ChrisWhite\B2\Client(B2_ACCOUNT_ID, B2_APPLICATION_KEY);

    $client= new \GuzzleHttp\Client();
    $res= $client->get($lossy);
    $orig_file= $res->getBody();

    $upload= $b2->upload([
      'BucketName' => B2_BUCKET,
      'FileName' => '/i/o/' . $uuid . '.' . $ext,
      'Body' => $orig_file
    ]);

    // Generate the standard size image (max 768px)
    $res= $short->reduceUrl($original, [
      'refresh' => 1,
      'resize' => 3,
      'resize_width' => 768,
      'resize_height' => 768,
    ]);

    $res= $client->get($res->LossyURL);
    $file= $res->getBody();

    $upload= $b2->upload([
      'BucketName' => B2_BUCKET,
      'FileName' => '/i/st/' . $uuid . '.jpg',
      'Body' => $file
    ]);

    // Generate the medium size image (max 384px)
    $res= $short->reduceUrl($original, [
      'refresh' => 1,
      'resize' => 3,
      'resize_width' => 384,
      'resize_height' => 384,
    ]);

    $res= $client->get($res->LossyURL);
    $file= $res->getBody();

    $upload= $b2->upload([
      'BucketName' => B2_BUCKET,
      'FileName' => '/i/md/' . $uuid . '.jpg',
      'Body' => $file
    ]);

    // Generate the thumbnail (max 128px)
    $res= $short->reduceUrl($original, [
      'refresh' => 1,
      'resize' => 3,
      'resize_width' => 128,
      'resize_height' => 128,
    ]);

    $res= $client->get($res->LossyURL);
    $file= $res->getBody();

    $upload= $b2->upload([
      'BucketName' => B2_BUCKET,
      'FileName' => '/i/th/' . $uuid . '.jpg',
      'Body' => $file
    ]);

    return $image;
  }

  public function jsonSerialize() {
    return $this->as_array();
  }
}
