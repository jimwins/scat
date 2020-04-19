<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Psr\Http\Message\RequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Respect\Validation\Validator as v;

class Ordure {
  protected $container;

  public function __construct(ContainerInterface $container) {
    $this->container= $container;
  }

  public function pushPrices(Request $req, Response $res, array $args) {
    $url= ORDURE . '/update-pricing';
    $key= ORDURE_KEY;

    $items= $this->container->catalog->getItems()
              ->select_many('retail_price','discount_type','discount')
              ->select_expr('(SELECT SUM(allocated)
                                FROM txn_line
                               WHERE item_id = item.id)',
                            'stock')
              ->select_many('code', 'minimum_quantity', 'purchase_quantity')
              ->find_many();

    /* Just in-memory, could be clever and build a stream interface. */
    $data= "retail_price\tdiscount_type\tdiscount\tstock\tcode\t".
           "minimum_quantity\tpurchase_quantity\r\n";

    foreach ($items as $item) {
      $data.= $item->retail_price . "\t" .
              ($item->discount_type ?: 'NULL') . "\t" .
              ($item->discount ?: 'NULL') . "\t" .
              ($item->stock ?: 'NULL') . "\t" .
              $item->code . "\t" .
              $item->minimum_quantity . "\t" .
              $item->purchase_quantity . "\r\n";
    }

    $client= new \GuzzleHttp\Client();

    $res= $client->request('POST', $url, [
                             //'debug' => true,
                             'multipart' => [
                               [
                                 'name' => 'prices', 
                                 'contents' => $data,
                                 'filename' => 'prices.txt',
                               ],
                               [
                                 'name' => 'key',
                                 'contents' => $key
                               ]
                             ],
                           ]);

    $body= $res->getBody();
  }
}
