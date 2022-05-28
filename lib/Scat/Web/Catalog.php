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
    $q= trim($request->getParam('q'));
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
            $original_q= $q;
            $q= trim(join(' ', $new_terms));
            $retry= true;
            goto retry; /* goto considered harmful. pfft. */
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
               @code, @is_dropshippable)
                 SET discount_type = IF(@discount_type = 'NULL', NULL,
                                        @discount_type),
                     discount = IF(@discount = 'NULL', NULL, @discount),
                     stock = IF(@stock = 'NULL', NULL, @stock)
          ";

      $stream= $file->getStream();

      $this->data->execute($q, [ ($stream->getMetaData())['uri'] ]);
      $rows+= $this->data->get_last_statement()->rowCount();
    }

    touch('/tmp/last-loaded-prices');

    return $response->withJson([ 'message' => "Loaded {$rows} prices." ]);
  }

  public function sitemap(Request $request, Response $response) {
    $products= $this->catalog->getProducts();
    return $this->view->render($response, 'catalog/sitemap.xml', [
      'products' => $products
    ])->withHeader('Content-type', 'text/xml;charset=UTF-8');
  }
}
