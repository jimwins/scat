<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Ordure {
  private $ordure_url, $ordure_key;

  public function __construct(
    private \Scat\Service\Config $config,
    private \Scat\Service\Txn $txn,
    private \Scat\Service\Data $data,
    private \Scat\Service\Email $email
  ) {
    $this->ordure_url = $config->get('ordure.url');
    $this->ordure_key= $config->get('ordure.key');
  }

  public function pushPrices(Response $response,
                              \Scat\Service\Catalog $catalog)
  {
    $url= $this->ordure_url . '/update-pricing';
    $key= $this->ordure_key;

    $items= $catalog
              ->getItems()
              ->select_many('id','active')
              ->select_many('retail_price','discount_type','discount')
              ->select_expr('IF(is_kit,
                                (SELECT MIN((SELECT SUM(allocated)
                                               FROM txn_line
                                              WHERE txn_line.item_id = kit_item.item_id))
                                   FROM kit_item
                                  WHERE kit_id = item.id),
                                (SELECT SUM(allocated)
                                   FROM txn_line
                                  WHERE item_id = item.id))',
                            'stock')
              ->select_many('code', 'minimum_quantity', 'purchase_quantity')
              ->select_expr('(SELECT MIN(purchase_quantity)
                                FROM vendor_item
                               WHERE item_id = item.id
                                 AND vendor_id = 7
                                 AND vendor_item.active
                                 AND NOT special_order)',
                            'is_dropshippable')
              ->find_many();

    /* Second version of this */
    $version= 2;

    /* Just in-memory, could be clever and build a stream interface. */
    $data= "id\tretail_price\tdiscount_type\tdiscount\t".
           "minimum_quantity\tpurchase_quantity\t".
           "stock\tactive\t".
           "code\tis_dropshippable\r\n";

    foreach ($items as $item) {
      $data.= $item->id . "\t" .
              $item->retail_price . "\t" .
              ($item->discount_type ?: 'NULL') . "\t" .
              ($item->discount ?: 'NULL') . "\t" .
              $item->minimum_quantity . "\t" .
              $item->purchase_quantity . "\t" .
              ($item->stock ?: 'NULL') . "\t" .
              $item->active . "\t" .
              $item->code . "\t" .
              ($item->is_dropshippable ?: '0') .
              "\r\n";
    }

    $client= new \GuzzleHttp\Client();

    $res= $client->request('POST', $url, [
                             //'debug' => $GLOBALS['DEBUG'],
                             'verify' => false, # XXX no SSL verification
                             'multipart' => [
                               [
                                 'name' => 'prices', 
                                 'contents' => $data,
                                 'filename' => 'prices.txt',
                               ],
                               [
                                 'name' => 'key',
                                 'contents' => $key
                               ],
                               [
                                 'name' => 'version',
                                 'contents' => $version
                               ]
                             ],
                           ]);

    $body= $res->getBody();

    return $response;
  }

  public function pullSignups(Request $request, Response $response) {
    $messages= [];

    $client= new \GuzzleHttp\Client();

    $url= $this->ordure_url . '/get-pending-rewards';
    $res= $client->request('GET', $url,
                           [
                             //'debug' => $GLOBALS['DEBUG'],
                             'verify' => false, # XXX no SSL verification
                             'query' => [ 'key' => $this->ordure_key ]
                           ]);

    $updates= json_decode($res->getBody());

    foreach ($updates as $update) {
      $person= null;

      /* First we look by number, and then by email. */
      if (!empty($update->loyalty_number)) {
        $person= $this->data->factory('Person')->where('loyalty_number',
                                                  $update->loyalty_number)
                                          ->find_one();
      }

      if (!$person && !empty($update->email)) {
        $person= $this->data->factory('Person')->where('email',
                                                  $update->email)
                                          ->find_one();
      }

      /* Didn't find them? Create them. */
      if (!$person) {
        $person= $this->data->factory('Person')->create();
        $person->name= $update->name;
        $person->email= $update->email;
        $person->setProperty('phone', $update->phone);
        $person->save();
      }
      /* Otherwise update name, email */
      else {
        if ($update->name) $person->name= $update->name;
        if ($update->email) $person->email= $update->email;
        if ($update->phone)
          $person->setProperty('phone', $update->phone);
        $person->save();
      }

      /* This may trigger an SMS message if it's a new signup. */
      if ($update->rewardsplus) {
        try {
          $person->setProperty('rewardsplus', $update->rewardsplus);
        } catch (\Exception $e) {
          $messages[]= "Exception: " . $e->getMessage();
        }
      }

      if ($update->subscribe) {
        try {
          $key= $this->config->get('mailerlite.key');
          $groupsApi= (new \MailerLiteApi\MailerLite($key))->groups();

          $subscriber= [
            'email' => $person->email,
            'fields' => [
              'name' => $person->name,
            ]
          ];

          $groupsApi->addSubscriber(
            $this->config->get('mailerlite.group_id'),
            $subscriber
          );
        } catch (\Exception $e) {
          $messages[]= "{$person->id}: Problem with newsletter signup: " . $e->getMessage();
        }
      }

      /* Handle code */
      try {
        if ($update->code) {
          $code= preg_replace('/[^0-9A-F]/i', '', $update->code);
          $created= substr($code, 0, 8);
          $id= substr($code, -8);
          $created= date("Y-m-d H:i:s", hexdec($created));
          $id= hexdec($id);

          $txn= $this->txn->fetchById($id);
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
        }
      } catch (\Exception $e) {
        $messages[]= "Exception: " . $e->getMessage();
      }

      $url= $this->ordure_url . '/mark-rewards-processed';
      $res= $client->request('POST', $url,
                             [
                               //'debug' => $GLOBALS['DEBUG'],
                               'verify' => false, # XXX no SSL verification
                               'query' => [ 'key' => $this->ordure_key,
                                            'id' => $update->id ]
                             ]);

    }

    $response->getBody()->write(join("\n", $messages));

    return $response;
  }

  public function pullOrders(Request $request, Response $response,
                              \Scat\Service\Shipping $shipping,
                              \Scat\Service\Giftcard $gift,
                              \Scat\Service\Tax $tax,
                              View $view)
  {
    $client= new \GuzzleHttp\Client();
    $messages= [];

    $url= $this->ordure_url . '/sale/list';
    $res= $client->request('GET', $url, [
      //'debug' => $GLOBALS['DEBUG'],
      'verify' => false, # XXX no SSL verification
      'headers' => [
        'Accept' => 'application/json',
      ],
      'query' => [
        'key' => $this->ordure_key,
        'status' => 'paid',
        'bust' => time(),
      ]
    ]);

    $sales= json_decode($res->getBody());

    foreach ($sales as $summary) {
      if ($summary->status != 'paid') {
        continue;
      }

      try {

        $url= $this->ordure_url . '/sale/' . $summary->uuid;
        $res= $client->request('GET', $url, [
          //'debug' => $GLOBALS['DEBUG'],
          'verify' => false, # XXX no SSL verification
          'headers' => [
            'Accept' => 'application/json',
          ],
          'query' => [
            'key' => $this->ordure_key
          ]
        ]);

        $data= json_decode($res->getBody());

        $this->data->beginTransaction();

        if ($data->sale->person_id) {
          $person=
            $this->data->factory('Person')->find_one($data->sale->person_id);
        } else {
          /* Prefer to find by email. */
          $person=
            $this->data->factory('Person')->where('email', $data->sale->email)
                                          ->where('active', 1)
                                          ->find_one();

          /* But try phone number next, if we have one */
          if (!$person && $data->sale->phone) {
            $loyalty_number= preg_replace('/\D+/', '', $data->sale->phone);
            $person=
              $this->data->factory('Person')->where('loyalty_number',
                                                    $loyalty_number)
                                            ->where('active', 1)
                                            ->find_one();
          }
        }

        /* Didn't find them? Create them. */
        if (!$person) {
          $person= $this->data->factory('Person')->create();
          $person->name= $data->sale->name;
          $person->email= $data->sale->email;
          $person->phone= $data->sale->phone;
          $person->save();
        }
        /* Otherwise update name, email */
        else {
          // XXX think about how/whether we want to change email and phone
          if ($data->sale->name) $person->name= $data->sale->name;
          if ($data->sale->email) $person->email= $data->sale->email;
          if ($data->sale->phone) $person->phone= $data->sale->phone;
          $person->save();
        }

        /* Create the base transaction */
        $txn= $this->txn->create('customer');
        $txn->uuid= $data->sale->uuid;
        $txn->online_sale_id= $data->sale->id;
        $txn->status= 'paid';
        // We update created to when the transaction was paid, otherwise it's
        // the cart creation date, which may be long ago
        $txn->created= $data->sale->modified;
        $txn->filled= $data->sale->modified;
        $txn->paid= $data->sale->modified;
        $txn->tax_captured= NULL;
        $txn->person_id= $person->id;
        $txn->tax_rate= 0.0;

        $txn->save();

        /* Add items */
        foreach ($data->items as $item) {
          $txn_line= $txn->items()->create();
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
        if ($data->sale->shipping != 0) {
          switch ($data->sale->shipping_method) {
          case 'bike':
            $code= 'ZZ-DELIVERY';
            break;
          case 'cargo_bike':
            $code= 'ZZ-DELIVERY-CARGO';
            break;
          case 'local_delivery':
          case 'local_sm':
          case 'local_md':
          case 'local_lg':
          case 'local_xl':
          case 'local_xxl':
          case 'local_xxxl':
            $code= 'ZZ-DELIVERY-VEHICLE';
            break;
          case 'economy':
            $code= 'ZZ-SHIPPING-ECONOMY';
            break;
          case 'two_day':
            $code= 'ZZ-SHIPPING-TWODAY';
            break;
          case 'next_day':
            $code= 'ZZ-SHIPPING-NEXTDAY';
            break;
          case 'default':
          default:
            $code= 'ZZ-SHIPPING-CUSTOM';
          }

          $item= $this->data->factory('Item')
                   ->where('code', $code)
                   ->find_one();

          $txn_line= $txn->items()->create();
          $txn_line->txn_id= $txn->id;
          $txn_line->item_id= $item->id;
          $txn_line->ordered= $txn_line->allocated= -1;
          $txn_line->tic= $item->tic;
          $txn_line->retail_price= $data->sale->shipping;
          $txn_line->tax= $data->sale->shipping_tax;
          $method= $data->sale->shipping_method;
          $txn_line->data= json_encode([
            'method' => $method,
            'details' => $data->sale->shipping_options->$method,
          ]);
          $txn_line->save();
        }

        /* Add payments */
        foreach ($data->payments as $pay) {
          $payment= $txn->payments()->create();
          $payment->txn_id= $txn->id;
          $payment->method= ($pay->method == 'credit') ? 'stripe' : $pay->method;
          $payment->processed= $pay->processed;
          $payment->captured= $pay->captured;

          if ($pay->method == 'credit') {
            $payment->cc_type= $pay->data->cc_brand;
            $payment->cc_lastfour= $pay->data->cc_last4;
          }

          if ($pay->method == 'gift') {
            /* Try to capture the payment. Not fatal, we'll have to sort
             * it out manually later. */
            try {
              $card= $pay->data->card;
              $gift->add_txn($card, -$pay->amount, $txn->id);
              $payment->set_expr('captured', 'NOW()');
            } catch (\Exception $e) {
              $messages[]=
                "{$txn->uuid}: Failed to capture gift card {$card}, " .
                $e->getMessage();
            }
          }

          if ($pay->method == 'other') {
            $payment->method= 'discount';
          }

          $payment->amount= $pay->amount;
          $payment->data= json_encode($pay->data);
          $payment->save();
        }

        /* Add shipping address */
        if ($data->sale->shipping_address_id != 1) {
          if ($data->shipping_address->easypost_id) {
            $easypost_address= $shipping->retrieveAddress(
              $data->shipping_address->easypost_id
            );
          } else {
            $easypost_address= $shipping->createAddress([
              'verify'  => [ 'delivery' ],
              'name' => $data->shipping_address->name ?: $data->sale->name,
              'email' => $data->shipping_address->email ?: $data->sale->email,
              'company' => $data->shipping_address->company,
              'street1' => $data->shipping_address->address1 ?? $data->shipping_address->street1,
              'street2' => $data->shipping_address->address2 ?? $data->shipping_address->street2,
              'city' => $data->shipping_address->city,
              'state' => $data->shipping_address->state,
              'zip' => $data->shipping_address->zip5 ?? $data->shipping_address->zip,
              'country' => 'US',
              'phone' => $data->shipping_address->phone ?: $data->sale->phone,
            ]);
          }

          $address= $this->data->factory('Address')->create();
          $address->easypost_id= $easypost_address->id;
          $address->name= $easypost_address->name;
          $address->email= $easypost_address->email ?: $data->sale->email;
          $address->company= $easypost_address->company;
          $address->street1= $easypost_address->street1;
          $address->street2= $easypost_address->street2;
          $address->city= $easypost_address->city;
          $address->state= $easypost_address->state;
          $address->zip= $easypost_address->zip;
          $address->country= $easypost_address->country;
          $address->phone= $easypost_address->phone ?: $data->sale->phone;
          $address->timezone=
            @$easypost_address->verifications->delivery->details->time_zone;
          $address->save();

          $txn->shipping_address_id= $address->id;
        } else {
          $txn->shipping_address_id= 1;
        }
        $txn->save();

        /* Add notes */
        foreach ($data->notes as $sale_note) {
          $note= $txn->notes()->create();
          $note->kind= 'txn';
          $note->attach_id= $txn->id;
          $note->person_id= $sale_note->person_id;
          $note->content= $sale_note->content;
          $note->added= $sale_note->added;
          $note->todo= 1;
          $note->save();
        }

        // Capture tax (not fatal if problem, we will re-try later)
        try {
          // Need to force a new lookup if person_id has changed
          $txn->captureTax($tax, $txn->person_id != $data->sale->person_id);
        } catch (\Exception $e) {
          $messages[]=
            "{$txn->uuid}: Failed to capture tax, " . $e->getMessage();
        }

        // Reward loyalty
        $txn->rewardLoyalty();

        // Mark status on Ordure

        $url= $this->ordure_url . '/sale/' . $summary->uuid . '/set-status';
        $res= $client->request('POST', $url,
                               [
                                 //'debug' => $GLOBALS['DEBUG'],
                                 'verify' => false, # XXX no SSL verification
                                 'headers' => [
                                   'X-Requested-With' => 'XMLHttpRequest',
                                 ],
                                 'form_params' => [
                                   'key' => $this->ordure_key,
                                   'status' => 'processing'
                                 ]
                               ]);

        $this->data->commit();

        $data= [ 'txn' => $txn, 'full_invoice' => true ];

        $template= $view->getEnvironment()->load('email/confirmed.html');

        $subject= $template->renderBlock('title', $data);
        $body= $template->render($data);

        $this->email->send([ $person->email => $person->name], $subject, $body);

        /* Attach email as a note */
        $note= $txn->createNote();
        $note->source= 'email';
        $note->content= $subject;
        $note->full_content= $body;
        $note->save();
      }
      catch (Exception $e) {
        $this->data->rollBack();
        $messages[]= "Exception: " . $e->getMessage();
      }
    }

    return $response;
  }

  public function processAbandonedCarts(Request $request, Response $response,
                                        View $view)
  {
    $client= new \GuzzleHttp\Client();

    $seen= [];

    $url= $this->ordure_url . '/sale/list';
    $res= $client->request('GET', $url, [
      //'debug' => $GLOBALS['DEBUG'],
      'verify' => false, # XXX no SSL verification
      'headers' => [
        'Accept' => 'application/json',
      ],
      'query' => [
        'key' => $this->ordure_key,
        'status' => 'cart',
        'yesterday' => 1,
      ]
    ]);

    $sales= json_decode($res->getBody());

    foreach ($sales as $summary) {
      if ($summary->status != 'cart') {
        continue;
      }

      $url= $this->ordure_url . '/sale/' . $summary->uuid;
      $res= $client->request('GET', $url, [
        //'debug' => $GLOBALS['DEBUG'],
        'verify' => false, # XXX no SSL verification
        'headers' => [
          'Accept' => 'application/json',
        ],
        'query' => [ 'key' => $this->ordure_key ]
       ]);

      $data= json_decode($res->getBody(), true);

      $email= $data['sale']['email'];

      if (!count($data['items']) || !$email) {
        continue;
      }

      if ($data['sale']['abandoned_level'] >= 1) {
        error_log("Skipping abandoned cart email: already sent\n");
        continue;
      }

      if (@$seen[$email]++) {
        error_log("Skipping abandoned cart email: already today to $email\n");
        continue;
      }

      // Look up this person
      $person= $this->data->factory('Person')->where('email', $email)->find_one();
      if ($person) {
        $recent_orders= $person->txns()->where_raw('created BETWEEN NOW() - INTERVAL 1 DAY AND NOW()')->find_many();
        if ($recent_orders) {
          error_log("Skipping abandoned cart email: $email already placed order today\n");
          continue;
        }
      }

      $url= $this->ordure_url . '/sale/' . $summary->uuid . '/set-abandoned-level';
      $res= $client->request('POST', $url,
                             [
                               //'debug' => $GLOBALS['DEBUG'],
                               'verify' => false, # XXX no SSL verification
                               'headers' => [
                                 'X-Requested-With' => 'XMLHttpRequest',
                               ],
                               'form_params' => [
                                 'key' => $this->ordure_key,
                                 'abandoned_level' => 2
                               ]
                             ]);

      $data['call_to_action_url']= $this->ordure_url . '/cart?uuid=' . $data['sale']['uuid'] . '&utm_source=internal&utm_medium=email&utm_id=abandoned-cart';

      $template= $view->getEnvironment()->load('email/abandoned-cart.html');

      $this->email->send([ $data['sale']['email'] => $data['sale']['name'] ],
                          $template->renderBlock('title', $data),
                          $template->render($data), [],
                          [ 'stream' => 'abandoned-cart' ]);
    }

    return $response;
  }

  public function fixLoyalty(Request $request, Response $response) {
    $txns= $this->txn->find('customer', 0, 100000)
                ->where_not_null('online_sale_id')
                ->find_many();

    foreach ($txns as $txn) {
      if (!count($txn->loyalty()->find_many())) {
        $txn->rewardLoyalty();
      }
    }

    return $response->withJson([]);
  }

}
