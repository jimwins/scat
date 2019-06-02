<?php
namespace Scat;

/*
 * This is a very thin wrapper around the ShortPixel.com API. It tries to be
 * less clever than the official PHP API wrapper, just providing bare-bones
 * access for processing a single URL or file.
 *
 * $sp= new ShortPixel(API_KEY, [ 'option' => 'value' ]);
 * $result= $sp->reduceUrl($url, [ 'option2' => 'value' ]);
 * $result= $sp->reduceFile($file, [ 'option3' => 'value' ]);
 *
 * It will throw a ShortPixelException if it has trouble.
 */

class ShortPixel {
  private $api_key;
  private $options;

  function __construct($key, $options= []) {
    $this->api_key= $key;
    $this->options= $options;
  }

  private function handleResponse($res) {
    $data= json_decode($res->getBody());

    $error= null;
    switch (json_last_error()) {
      case JSON_ERROR_NONE:
	break;
      case JSON_ERROR_DEPTH:
        $error= 'Maximum stack depth exceeded';
	break;
      case JSON_ERROR_STATE_MISMATCH:
        $error= 'Underflow or the modes mismatch';
        break;
      case JSON_ERROR_CTRL_CHAR:
	$error= 'Unexpected control character found';
	break;
      case JSON_ERROR_SYNTAX:
	$error= 'Syntax error, malformed JSON';
	break;
      case JSON_ERROR_UTF8:
	$error= 'Malformed UTF-8 characters, possibly incorrectly encoded';
	break;
      default:
	$error= 'Unknown JSON decoding error';
    }

    if ($error) {
      throw new ShortPixelException($error);
    }

    // We only handle a single file at a time.
    $file= $data[0];

    if (!isset($file->Status)) {
      var_dump($file);
      throw new ShortPixelException("ShortPixel did not return a status!");
    }

    if ($file->Status->Code <= 0) {
      throw new ShortPixelException($file->Status->Message,
                                    $file->Status->Code);
    }

    return $file;
  }

  function reduceUrl($url, $options= []) {
    $client= new \GuzzleHttp\Client();

    $params= array_merge([
                          'key' => $this->api_key,
                          'plugin_version' => 'SCAT0',
                          'urllist' => [
                            $url
                          ]
                         ],
                         $this->options,
                         $options);

    $api_url= 'https://api.shortpixel.com/v2/reducer.php';
    $res= $client->request('POST', $api_url, [
                             \GuzzleHttp\RequestOptions::JSON => $params
                          ]);

    return $this->handleResponse($res);
  }

  function reduceFile($file, $options= []) {
    $client= new \GuzzleHttp\Client();

    if (!is_resource($file)) {
      $fd= @fopen($file, 'r');
      if (!$fd)
        throw new ShortPixelException("Unable to open file '$file'.");
      $file= $fd;
    }

    $params= array_merge([
                          'key' => $this->api_key,
                          'plugin_version' => 'SCAT0',
                          'file_paths' => json_encode([
                            'file1' => 'dummy_path.jpg'
                          ]),
                          'file1' => $file
                         ],
                         $this->options,
                         $options);
    $multipart= array_map(function ($k, $v) {
      return [ 'name' => $k, 'contents' => $v ];
    }, array_keys($params), $params);

    $api_url= 'https://api.shortpixel.com/v2/post-reducer.php';
    $res= $client->request('POST', $api_url, [
                             'multipart' => $multipart
                          ]);

    return $this->handleResponse($res);
  }
}

// Nothing to this, just giving them a name.
class ShortPixelException extends \Exception {
}
