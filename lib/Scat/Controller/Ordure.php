<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Respect\Validation\Validator as v;

class Ordure {

  public function pushPrices(Response $response,
                              \Scat\Service\Catalog $catalog) {
    $url= ORDURE . '/update-pricing';
    $key= ORDURE_KEY;

    $items= $catalog
              ->getItems()
              ->select_many('retail_price','discount_type','discount')
              ->select_expr('(SELECT SUM(allocated)
                                FROM txn_line
                               WHERE item_id = item.id)',
                            'stock')
              ->select_many('code', 'minimum_quantity', 'purchase_quantity')
              ->select_expr('(SELECT MIN(purchase_quantity)
                                FROM vendor_item
                               WHERE item_id = item.id
                                 AND vendor_id = 7
                                 AND NOT special_order)',
                            'is_dropshippable')
              ->find_many();

    /* Just in-memory, could be clever and build a stream interface. */
    $data= "retail_price\tdiscount_type\tdiscount\tstock\tcode\t".
           "minimum_quantity\tpurchase_quantity\tis_dropshippable\r\n";

    foreach ($items as $item) {
      $data.= $item->retail_price . "\t" .
              ($item->discount_type ?: 'NULL') . "\t" .
              ($item->discount ?: 'NULL') . "\t" .
              ($item->stock ?: 'NULL') . "\t" .
              $item->code . "\t" .
              $item->minimum_quantity . "\t" .
              $item->purchase_quantity . "\t" .
              ($item->is_dropshippable ?: '0') . "\r\n";
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

    return $response;
  }

  public function pullSignups(Request $request, Response $response) {
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

  public function pullOrders(Request $request, Response $response) {
    $messages= [];

    $client= new \GuzzleHttp\Client();

    \EasyPost\EasyPost::setApiKey(EASYPOST_KEY);

    $url= ORDURE . '/sale/list';
    $res= $client->request('GET', $url,
                           [
                             'debug' => $DEBUG,
                             'query' => [ 'key' => ORDURE_KEY,
                                          'json' => 1 ]
                           ]);

    $sales= json_decode($res->getBody());

    foreach ($sales as $summary) {
      if ($summary->status != 'paid') {
        continue;
      }

      try {

        $url= ORDURE . '/sale/' . $summary->uuid . '/json';
        $res= $client->request('GET', $url,
                               [
                                 'debug' => $DEBUG,
                                 'query' => [ 'key' => ORDURE_KEY ]
                               ]);

        $data= json_decode($res->getBody());

        \ORM::get_db()->beginTransaction();

        $person= \Model::factory('Person')->where('email', $data->sale->email)
                                          ->where('active', 1)
                                          ->find_one();

        /* Didn't find them? Create them. */
        if (!$person) {
          $person= \Model::factory('Person')->create();
          $person->name= $data->sale->name;
          $person->email= $data->sale->email;
          $person->save();

          $messages[]= "Created new person '{$person->name}'.";
        }
        /* Otherwise update name, email */
        else {
          if ($data->sale->name) $person->name= $data->sale->name;
          if ($data->sale->email) $person->email= $data->sale->email;
          $person->save();

          $messages[]= "Updated details for person '{$person->name}'.";
        }

        $number= \ORM::for_table('txn')
                  ->where('type', 'customer')->max('number');

        /* Create the base transaction */
        $txn= \Model::factory('Txn')->create();
        $txn->uuid= $data->sale->uuid;
        $txn->number= $number + 1;
        $txn->created= $data->sale->created;
        $txn->filled= $data->sale->modified;
        $txn->paid= $data->sale->modified;
        $txn->type= 'customer';
        $txn->person_id= $person->id;
        $txn->tax_rate= 0.0;

        $txn->save();

        /* Add items */
        foreach ($data->items as $item) {
          $txn_line= \Model::factory('TxnLine')->create();
          $txn_line->txn_id= $txn->id;
          $txn_line->item_id= $item->item_id;
          $txn_line->ordered= $txn_line->allocated= -1 * ($item->quantity);
          $txn_line->override_name= $item->override_name;
          $txn_line->retail_price= $item->retail_price;
          $txn_line->discount_type= $item->discount_type;
          $txn_line->discount= $item->discount;
          $txn_line->discount_manual= $item->discount_manual;
          $txn_line->tic= $item->tic;
          $txn_line->tax= $item->tax;
          $txn_line->save();
        }

        /* Add shipping item */
        if ($data->sale->shipping) {
          $item= \Model::factory('Item')
                   ->where('code','ZZ-SHIPPING-CUSTOM')
                   ->find_one();

          $txn_line= \Model::factory('TxnLine')->create();
          $txn_line->txn_id= $txn->id;
          $txn_line->item_id= $item->id;
          $txn_line->ordered= $txn_line->allocated= -1;
          $txn_line->tic= $item->tic;
          $txn_line->retail_price= $data->sale->shipping;
          $txn_line->tax= $data->sale->shipping_tax;
          $txn_line->save();
        }

        /* Add payments */
        foreach ($data->payments as $pay) {
          $payment= \Model::factory('Payment')->create();
          $payment->txn_id= $txn->id;
          $payment->method= ($pay->method == 'credit') ? 'stripe' : $pay->method;
          $payment->amount= $pay->amount;
          $payment->processed= $pay->processed;
          $payment->save();
        }

        /* Add a note */
        $note= \Model::factory('Note')->create();
        $note->kind= 'txn';
        $note->attach_id= $txn->id;
        if ($data->sale->shipping_address_id == 1) {
          $note->content= "Need to pick for in-store pick-up. Contact customer when ready!";
        } else {
          $note->content= "Need to pick & ship online order.";
        }
        $note->todo= 1;
        $note->save();

        /* Add shipping address */
        if ($data->sale->shipping_address_id != 1) {
          $easypost_address= \EasyPost\Address::create([
            'name' => $data->shipping_address->name,
            'company' => $data->shipping_address->company,
            'street1' => $data->shipping_address->address1,
            'street2' => $data->shipping_address->address2,
            'city' => $data->shipping_address->city,
            'state' => $data->shipping_address->state,
            'zip' => $data->shipping_address->zip5,
            'country' => 'US',
            'phone' => $data->shipping_address->phone,
          ]);

          $address= \Model::factory('Address')->create();
          $address->easypost_id= $easypost_address->id;
          $address->name= $easypost_address->name;
          $address->company= $easypost_address->company;
          $address->street1= $easypost_address->street1;
          $address->street2= $easypost_address->street2;
          $address->city= $easypost_address->city;
          $address->state= $easypost_address->state;
          $address->zip= $easypost_address->zip;
          $address->country= $easypost_address->country;
          $address->phone= $easypost_address->phone;
          $address->save();

          $txn->shipping_address_id= $address->id;
          $txn->save();
        }

        $url= ORDURE . '/sale/' . $summary->uuid . '/set-status';
        $res= $client->request('POST', $url,
                               [
                                 'debug' => $DEBUG,
                                 'headers' => [
                                   'X-Requested-With' => 'XMLHttpRequest',
                                 ],
                                 'form_params' => [
                                   'key' => ORDURE_KEY,
                                   'status' => 'processing'
                                 ]
                               ]);

        \ORM::get_db()->commit();

        $messages[]= "Created transaction for sale {$data->sale->id}.";
      }
      catch (Exception $e) {
        \ORM::get_db()->rollBack();
        throw $e;
      }
    }

    $response->getBody()->write(join("\n", $messages));

    return $response;
  }

  public function processAbandonedCarts(Request $request, Response $response) {
    $loader= new \Twig\Loader\FilesystemLoader('../ui');
    $twig= new \Twig\Environment($loader, [
      'cache' => false
    ]);

    $exit= 0;

    $client= new \GuzzleHttp\Client();

    $url= ORDURE . '/sale/list';
    $res= $client->request('GET', $url,
                           [
                             'debug' => $DEBUG,
                             'query' => [ 'key' => ORDURE_KEY,
                                          'carts' => 1,
                                          'yesterday' => 1,
                                          'json' => 1 ]
                           ]);

    $sales= json_decode($res->getBody());

    foreach ($sales as $summary) {
      if ($summary->status != 'cart') {
        continue;
      }

      $url= ORDURE . '/sale/' . $summary->uuid . '/json';
      $res= $client->request('GET', $url,
                             [
                               'debug' => $DEBUG,
                               'query' => [ 'key' => ORDURE_KEY ]
                             ]);

      $data= json_decode($res->getBody(), true);

      if (!count($data['items']) || !$data['sale']['email']) {
        continue;
      }

      $data['call_to_action_url']= ORDURE . '/cart?uuid=' . $data['sale']['uuid'];

      $template= $twig->load('email/abandoned-cart.html');

      $httpClient= new \Http\Adapter\Guzzle6\Client(new \GuzzleHttp\Client());
      $sparky= new \SparkPost\SparkPost($httpClient,
                                        [ 'key' => SPARKPOST_KEY ]);

      $promise= $sparky->transmissions->post([
        'content' => [
          'html' => $template->render($data),
          'subject' => $template->renderBlock('title', $data),
          'from' => array('name' => "Raw Materials Art Supplies",
                          'email' => OUTGOING_EMAIL_ADDRESS),
          'inline_images' => [
            [
              'name' => 'logo.png',
              'type' => 'image/png',
              'data' => base64_encode(file_get_contents(basename(__DIR__).
                                                        '/../../../ui/logo.png')),
            ],
          ],
        ],
        'recipients' => [
          [
            'address' => [
              'name' => $data['sale']['name'],
              'email' => $data['sale']['email'],
            ],
          ],
          [
            // BCC ourselves
            'address' => [
              'header_to' => $data['sale']['email'],
              'email' => OUTGOING_EMAIL_ADDRESS,
            ],
          ],
        ],
        'options' => [
          'inlineCss' => true,
        ],
      ]);

      try {
        $res= $promise->wait();

      } catch (\Exception $e) {
        error_log(sprintf("SparkPost failure: %s (%s)",
                          $e->getMessage(), $e->getCode()));
      }
    }

    return $response;
  }

}
