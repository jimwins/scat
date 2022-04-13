<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Catalog {
  private $catalog, $view, $data;

  public function __construct(
    \Scat\Service\Catalog $catalog,
    \Scat\Service\Data $data,
    View $view
  ) {
    $this->catalog= $catalog;
    $this->data= $data;
    $this->view= $view;
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

}
