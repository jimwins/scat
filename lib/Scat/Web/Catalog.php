<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Catalog {
  public function __construct(
    private \Scat\Service\Catalog $catalog,
    private \Scat\Service\Data $data,
    private View $view
  ) {
  }

  public function search(Request $request, Response $response,
                          \Scat\Service\Search $search)
  {
    $q= trim($request->getParam('q') ?? '');
    $original_q= null;

    /* Check for a direct match to a item code */
    if ($q && preg_match('!^[-A-Z0-9/.]+$!i', $q)) {
      $item= $this->catalog->getItemByCode($q);
      if ($item && $item->active) {
        $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
        $routeParser= $routeContext->getRouteParser();
        return $response->withRedirect(
          $routeParser->urlFor(
            'catalog',
            $item->url_params(),
            [ 'q' => $q ] /* preserve $q */
          )
        );
      }
    }

    $error= $products= null;

    try {
      if ($q) {
        $retry= false;
        $original_q= false;
      retry:
        $products= $search->searchProducts($q, 100);

        /* No match and we haven't retried? Fuzz the query and retry. */
        if (!count($products) && !$retry) {
          $terms= preg_split('/\\s+/', $q);
          $new_terms= [];
          $changed= 0;

          foreach ($terms as $term) {
            $suggest= $search->suggestTerm($term);
            if (strcasecmp($suggest, $term) != 0) {
              $changed++;
            }
            $new_terms[]= $suggest;
          }

          if ($changed && count($new_terms)) {
            $new_q= trim(join(' ', $new_terms));
            if ($new_q) {
              $original_q= $q;
              $q= $new_q;
              $retry= true;
              goto retry; /* goto considered harmful. pfft. */
            }
          }
        }
      }
    } catch (\Exception $ex) {
      $error= $ex->getMessage();
      error_log("got error searching for '{$q}': $error\n");
    }

    return $this->view->render($response, 'catalog/searchresults.html', [
      'products' => $products,
      'q' => $q,
      'original_q' => $original_q,
      'error' => $error,
    ]);
  }

  public function updatePricing(Request $request, Response $response,
                                \Scat\Service\Config $config)
  {
    $key= $request->getParam('key');
    $version= (int)$request->getParam('version');

    if ($version != 2) {
      throw new \Exception("Don't know how to handle version {$version}");
    }

    if ($key != $config->get('ordure.key')) {
      throw new \Exception("Wrong key.");
    }

    $this->data->beginTransaction();

    /* Mark everything inactive until told otherwise. */
    $this->data->execute("UPDATE item_status SET active = 0");

    $rows= 0;
    foreach ($request->getUploadedFiles() as $file) {
      $q= "LOAD DATA LOCAL INFILE ?
             REPLACE
                INTO TABLE item_status
              FIELDS TERMINATED BY '\t'
              IGNORE 1 LINES
              (id, retail_price, @discount_type, @discount,
               minimum_quantity, purchase_quantity,
               @stock, active,
               @code, is_in_warehouse)
                 SET discount_type = IF(@discount_type = 'NULL', NULL,
                                        @discount_type),
                     discount = IF(@discount = 'NULL', NULL, @discount),
                     stock = IF(@stock = 'NULL', NULL, @stock)
          ";

      $stream= $file->getStream();

      $this->data->execute($q, [ ($stream->getMetaData())['uri'] ]);
      $rows+= $this->data->get_last_statement()->rowCount();
    }

    $this->data->commit();

    touch('/tmp/last-loaded-prices');

    return $response->withJson([ 'message' => "Loaded {$rows} prices." ]);
  }

  public function sitemap(Request $request, Response $response) {
    $products= $this->catalog->getProducts();
    return $this->view->render($response, 'catalog/sitemap.xml', [
      'products' => $products
    ])->withHeader('Content-type', 'text/xml;charset=UTF-8');
  }

  public function wordforms(Request $request, Response $response) {
    $wordforms= $this->data->Factory('Wordform')->find_many();

    $body= $response->getBody();
    foreach ($wordforms as $wordform) {
      $body->write($wordform->source . ' => ' . $wordform->dest . "\n");
    }
    return $response->withHeader('Content-type', 'text/plain;charset=UTF-8');
  }


  public function status(Request $request, Response $response) {
    $file= '/tmp/last-loaded-prices';
    if (file_exists($file) &&
        filemtime($file) > time() - (15 * 60)) {
      return $response->withJson([ 'status' => "Prices are current." ]);
    }
    return $response->withJson(['status' => "ERROR: Prices are not current."]);
  }

  public function grabImage(Request $request, Response $response,
                            \Scat\Service\Auth $auth,
                            \Scat\Service\Media $media)
  {
    $key= $request->getParam('key');

    if (!$auth->verify_access_key($key))
    {
      throw new \Slim\Exception\HttpForbiddenException($request, "Wrong key");
    }

    $url= $request->getParam('url');

    // Special hack to get full-size Salsify images
    $url= str_replace('/c_limit,cs_srgb,h_600,w_600', '', $url);
    // get jpeg instead of tiff
    $url= preg_replace('/tiff?$/i', 'jpg', $url);

    error_log("Grabbing image from URL '$url'\n");

    $client= new \GuzzleHttp\Client();
    $res= $client->get($url);

    $file= $res->getBody();
    $name= basename(parse_url($url, PHP_URL_PATH));

    if ($res->hasHeader('Content-Type')) {
      $content_type= $res->getHeader('Content-Type')[0];
      if (!preg_match('/^image/', $content_type)) {
        throw new \Exception("URL was not an image, it was a '$content_type'");
      }
    }

    $uuid= sprintf("%08x%02x%s", time(), 1, bin2hex(random_bytes(8)));

    // No extension? Probably a JPEG
    $ext= $request->getParam('ext') ?: pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg';

    $b2= $media->getB2Client();
    $b2_bucket= $media->getB2Bucket();

    $b2_file= $b2->upload([
      'BucketName' => $b2_bucket,
      'FileName' => "i/o/$uuid.$ext",
      'Body' => $file,
    ]);

    $path= sprintf(
      '%s/file/%s/%s',
      $b2->getAuthorization()['downloadUrl'],
      $b2_bucket,
      $b2_file->getName()
    );

    return $response->withJson([
      'path' => $path,
      'ext' => $ext,
      'uuid' => $uuid,
      'name' => $name,
      'id' => $b2_file->getId()
    ]);
  }
}
