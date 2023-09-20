<?php
namespace Scat\Service;

class Ordure
{
  public $url;
  public $key;
  public $static_url;

  public function __construct(Config $config) {
    $this->url= $config->get('ordure.url');
    $this->key= $config->get('ordure.key');
    $this->static_url= $config->get('ordure.static_url');
  }

  public function markOrderShipped($sale_uuid) {
    $url= $this->url . '/sale/' . $sale_uuid . '/set-status';

    $client= new \GuzzleHttp\Client();
    $res= $client->request('POST', $url,
                           [
                             'verify' => false, # XXX no SSL verification
                             'headers' => [
                               'X-Requested-With' => 'XMLHttpRequest',
                             ],
                             'form_params' => [
                               'key' => $this->key,
                               'status' => 'shipped'
                             ]
                           ]);
    // XXX do something with $res?
  }

  public function grabImage($url, $extra= []) {
    $client= new \GuzzleHttp\Client();
    $res= $client->request('POST', $this->url . '/~grab-image',
                           [
                             'verify' => false, # XXX no SSL verification
                             'headers' => [
                               'X-Requested-With' => 'XMLHttpRequest',
                             ],
                             'form_params' => array_merge([
                               'key' => $this->key,
                               'url' => $url,
                             ], $extra)
                           ]);

    return json_decode($res->getBody());
  }

  public function createCartFromTxn($txn) {
    $url= $this->url . '/cart';

    $client= new \GuzzleHttp\Client();
    $jar= new \GuzzleHttp\Cookie\CookieJar();

    $data= [];

    $person= $txn->person();
    if ($person) {
      $data= [
        'email' => $person->email,
        'name' => $person->name,
        'phone' => $person->phone,
      ];
    }

    // create the cart
    $res= $client->request('POST', $url,
                           [
                             'cookies' => $jar,
                             'headers' => [ 'Accept' => 'application/json' ],
                             'form_params' => $data,
                           ]);

    if ($txn->shipping_address_id > 1) {
      $data= [
        'address' => $txn->shipping_address()
      ];
      $res= $client->request('POST', $url,
                             [
                               'cookies' => $jar,
                               'headers' => [ 'Accept' => 'application/json' ],
                               \GuzzleHttp\RequestOptions::JSON => $data,
                             ]);
    } elseif ($txn->shipping_address_id == 1) {
      $res= $client->request('GET', $url . '/checkout/set-pickup',
                             [
                               'cookies' => $jar,
                               'headers' => [ 'Accept' => 'application/json' ]
                             ]);
    }

    foreach ($txn->items()->find_many() as $item) {
      if ($item->tic == '11000') {
        $data= [
          'manual_shipping' => [
            'key' => $this->key,
            'shipping_cost' => $item->retail_price
          ]
        ];
        $res= $client->request('POST', $url,
                               [
                                 'cookies' => $jar,
                                 'headers' => [ 'Accept' => 'application/json' ],
                                 'form_params' => $data,
                               ]);
        continue;
      }

      $data= [
        'item' => $item->item()->code, // just $item->code() sometimes hides the real code
        'quantity' => - $item->ordered,
        // We send a key so we can set these other values
        'key' => $this->key,
        'retail_price' => $item->retail_price,
        'discount_type' => $item->discount_type,
        'discount' => $item->discount,
        'discount_manual' => $item->discount_manual,
        'override_name' => $item->override_name,
      ];

      $res= $client->request('POST', $url . '/add-item',
                             [
                               'cookies' => $jar,
                               'headers' => [ 'Accept' => 'application/json' ],
                               'form_params' => $data,
                             ]);
    }

    $cart_id= $jar->getCookieByName('cartID')->getValue();

    $note= $txn->createNote();
    $note->content= "Created online cart: {$this->url}/cart?uuid={$cart_id}";
    $note->save();
  }
}
