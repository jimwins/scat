<?php
namespace Scat\Service;

class Push
{
  protected $config;

  public function __construct($config) {
    $this->config= $config;
  }

  public function getPushPackage() {
    $certificate= $this->config['certificate'];
    $password= $this->config['password'];

    $raw_files= [
      'icon.iconset/icon_16x16.png',
      'icon.iconset/icon_16x16@2x.png',
      'icon.iconset/icon_32x32.png',
      'icon.iconset/icon_32x32@2x.png',
      'icon.iconset/icon_128x128.png',
      'icon.iconset/icon_128x128@2x.png',
      /*
       * Safari doesn't like larger sizes!
      'icon.iconset/icon_256x256.png',
      'icon.iconset/icon_256x256@2x.png',
      'icon.iconset/icon_512x512.png',
      */
    ];

    $tmp= sys_get_temp_dir() . '/' . uniqid();
    mkdir($tmp);
    mkdir($tmp . '/icon.iconset');

    $manifest= [];

    foreach ($raw_files as $file) {
      copy("ui/$file", "$tmp/$file");
      $manifest[$file]= [
        'hashType' => 'sha512',
        'hashValue' => hash('sha512', file_get_contents("$tmp/$file"))
      ];
    }

    $website_json= [
      "websiteName" => $this->config['websiteName'],
      "websitePushID" => $this->config['websitePushID'],
      "allowedDomains" => $this->config['allowedDomains'],
      "urlFormatString" => $this->config['urlFormatString'],
      /* XXX This should be some sort of person identifier. */
      "authenticationToken" => "19f8d7a6e9fb8a7f6d9330dabe",
      "webServiceURL" => $this->config['webServiceURL'],
    ];

    file_put_contents("$tmp/website.json",
                      /* Easier to debug if it is pretty. */
                      json_encode($website_json, JSON_PRETTY_PRINT));

    $manifest['website.json']= [
      'hashType' => 'sha512',
      'hashValue' => hash('sha512', file_get_contents("$tmp/website.json"))
    ];

    file_put_contents("$tmp/manifest.json",
                      /* Easier to debug if it is pretty. */
                      json_encode((object)$manifest, JSON_PRETTY_PRINT));

    $cert= file_get_contents($certificate);
    $cert_data= openssl_x509_read($cert);
    $private_key= openssl_pkey_get_private($cert, $password);

    openssl_pkcs7_sign("$tmp/manifest.json", "$tmp/signature",
                       $cert_data, $private_key, [],
                       PKCS7_BINARY | PKCS7_DETACHED);

    /* This is dumb, but there appears to be no way to create DER directly. */
    $pem= file_get_contents("$tmp/signature");
    $m= [];
    /* Seriously, we're parsing a well-known file format with regex. Dumb. */
    if (!preg_match('~Content-Disposition:[^\n]+\s*?([A-Za-z0-9+=/\r\n]+)\s*?-----~', $pem, $m)) {
      throw new \Exception("Unable to extract DER from PEM");
    }
    file_put_contents("$tmp/signature", base64_decode($m[1]));

    $zip_path= sys_get_temp_dir() . '/' . uniqid() . ".zip";

    $zip= new \ZipArchive();
    if (!$zip->open($zip_path, \ZIPARCHIVE::CREATE)) {
      throw new \Exception("Could not create ZIP at '$zip_path'");
    }

    foreach ($raw_files as $file) {
      $zip->addFile("$tmp/$file", $file);
    }
    $zip->addFile("$tmp/website.json", 'website.json');
    $zip->addFile("$tmp/manifest.json", 'manifest.json');
    $zip->addFile("$tmp/signature", 'signature');
    
    $zip->close();

    // XXX clean up

    return new \GuzzleHttp\Psr7\LazyOpenStream($zip_path, 'r');
  }

  public function sendNotification($token, $title, $body, $action, ...$args) {
    $payload= [
      'aps' => [
        'alert' => [
          'title' => $title,
          'body' => $body,
          'action' => $action,
        ],
        'url-args' => $args
      ]
    ];

    $payload= json_encode($payload);
    $apnsHost= 'gateway.push.apple.com';
    $apnsPort= 2195;
    $apnsCert= $this->config['certificate'];

    $streamContext= stream_context_create();
    stream_context_set_option($streamContext, 'ssl', 'local_cert', $apnsCert);
    $apns= stream_socket_client('ssl://' . $apnsHost . ':' . $apnsPort,
                                $error, $errorString,
                                2/* timeout */,
                                STREAM_CLIENT_CONNECT, $streamContext);
    $apnsMessage= chr(0) . chr(0) . chr(32) .
                  pack('H*', str_replace(' ', '', $token)) . chr(0) .
                  chr(strlen($payload)) . $payload;
    fwrite($apns, $apnsMessage);
    fclose($apns);
  }
}
