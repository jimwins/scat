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

  public function pullSignups(Request $req, Response $response, array $args) {
    $exit= 0;
    $messages= [];

    $client= new \GuzzleHttp\Client();

    $url= ORDURE . '/get-pending-rewards';
    $res= $client->request('GET', $url,
                           [
                             'debug' => $DEBUG,
                             'query' => [ 'key' => ORDURE_KEY ]
                           ]);

    $updates= json_decode($res->getBody());

    foreach ($updates as $update) {
      $person= null;

      /* First we look by number, and then by email. */
      if (!empty($update->loyalty_number)) {
        $person= \Model::factory('Person')->where('loyalty_number',
                                                  $update->loyalty_number)
                                          ->find_one();
      }

      if (!$person && !empty($update->email)) {
        $person= \Model::factory('Person')->where('email',
                                                  $update->email)
                                          ->find_one();
      }

      /* Didn't find them? Create them. */
      if (!$person) {
        $person= \Model::factory('Person')->create();
        $person->name= $update->name;
        $person->email= $update->email;
        $person->phone= $update->phone;
        if (!empty($update->loyalty_number))
          $person->loyalty_number= $update->loyalty_number;
        $person->save();

        $messages[]=
          "Created new person '".($person->name?:$person->email)."'";
      }
      /* Otherwise update name, email */
      else {
        if ($update->name) $person->name= $update->name;
        if ($update->email) $person->email= $update->email;
        $person->save();

        $messages[]=
          "Updated details for person '".($person->name?:$person->email)."'";
      }

      /* Handle code */
      try {
        if ($update->code) {
          $code= preg_replace('/[^0-9A-F]/i', '', $update->code);
          $created= substr($code, 0, 8);
          $id= substr($code, -8);
          $created= date("Y-m-d H:i:s", hexdec($created));
          $id= hexdec($id);

          $txn= Model::factory('Txn')->find_one($id);
          if (!$txn) {
            throw new \Exception("No such transaction found for '{$update->code}'");
          }

          if ($txn->person_id && $txn->person_id != $person->id) {
            throw new \Exception("Transaction {$id} already assigned to someone else.");
          }

          if ($txn->created != $created) {
            throw new \Exception("Timestamps for transaction {$id} don't match. '{$created}' != '{$txn->created}'");
          }

          $txn->person_id= $person->id;
          $txn->save();

          $messages[]= "Attached transaction {$id} to person.";
        }
      } catch (\Exception $e) {
        $messages[]= "Exception: " . $e->getMessage();
        $exit= 1;
      }

      $url= ORDURE . '/mark-rewards-processed';
      $res= $client->request('GET', $url,
                             [
                               'debug' => $DEBUG,
                               'query' => [ 'key' => ORDURE_KEY,
                                            'id' => $update->id ]
                             ]);

      $messages[]=
        "Completed update for '".($update->name?:$update->email)."'.";
    }

    $response->getBody()->write(join("\n", $messages));

    return $response;
  }
}
