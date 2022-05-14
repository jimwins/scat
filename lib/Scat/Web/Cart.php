<?php
namespace Scat\Web;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Cart {
  private $view, $catalog, $data;

  public function __construct(
    \Scat\Service\Data $data,
    \Scat\Service\Catalog $catalog,
    \Scat\Service\Config $config,
    \Scat\Service\Auth $auth,
    View $view
  ) {
    $this->data= $data;
    $this->auth= $auth;
    $this->catalog= $catalog;
    $this->config= $config;
    $this->view= $view;
  }

  public function get_amzn_client() {
    $config= [
      'public_key_id' => $this->config->get('amazon.public_key_id'),
      'private_key'   => $this->config->get('amazon.private_key'),
      'region'        => 'US',
    ];
    if ($GLOBALS['DEBUG']) {
      $config['sandbox']= true;
    }

    return new \Amazon\Pay\API\Client($config);
  }

  public function get_amzn_environment(Request $request, $uuid) {
    $merchant_id= $this->config->get('amazon.merchant_id');
    if (!$merchant_id) return null;

    $client= $this->get_amzn_client();

    $uri= $request->getUri();
    $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
    $link= $routeContext->getRouteParser()->fullUrlFor(
      $uri,
      'checkout-amzn',
      [],
      [ 'uuid' => $uuid ]
    );

    $payload= [
      'webCheckoutDetails' => [
        'checkoutReviewReturnUrl' => $link,
      ],
      'storeId' => $this->config->get('amazon.client_id'),
      'deliverySpecifications' => [
        'addressRestrictions' => [
          'type' => 'Allowed',
          'restrictions' => [
            'US' => [
              'zipCodes' => [ '*' ],
            ],
          ],
        ],
      ],
    ];

    $json_payload= json_encode($payload);

    $signature= $client->generateButtonSignature($json_payload);

    return [
      'merchant_id' => $merchant_id,
      'public_key_id' => $this->config->get('amazon.public_key_id'),
      'payload' => $json_payload,
      'signature' => $signature,
    ];
  }

  public function cart(Request $request, Response $response)
  {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    return $this->view->render($response, 'cart/index.html', [
      'person' => $person,
      'cart' => $cart,
      'amzn' => $this->get_amzn_environment($request, $cart->uuid),
    ]);
  }

  public function addItem(Request $request, Response $response)
  {
    $cart= $request->getAttribute('cart');

    // attach it to person, if logged in

    $item_code= trim($request->getParam('item'));
    $quantity= max((int)$request->getParam('quantity'), 1);

    if ($cart->closed()) {
      throw new \Exception("Cart already completed, start another one.");
    }

    // get item details
    $item= $this->catalog->getItemByCode($item_code);
    if (!$item) {
      throw new \Slim\Exception\HttpNotFoundException($request);
    }

    /* If this is a brand new cart, it won't have an ID yet. Save to create! */
    if (!$cart->id) {
      $cart->save();
    }

    // TODO handle kits
    // TODO add quantity to existing line instead of changing quantity

    $line= $cart->items()->create([
      'sale_id' => $cart->id,
      'item_id' => $item->id,
    ]);

    // TODO update price on existing lines
    $line->retail_price= $item->retail_price;
    $line->discount= $item->discount;
    $line->discount_type= $item->discount_type;
    $line->tic= $item->tic;

    $line->quantity+= $quantity;

    $line->save();

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($cart);
    }

    return $response->withRedirect('/cart');
  }

  public function amznCheckout(
    Request $request, Response $response,
    \Scat\Service\Shipping $shipping,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    $amzn_session_id= $request->getParam('amazonCheckoutSessionId');

    $client= $this->get_amzn_client();

    $res= $client->getCheckoutSession($amzn_session_id);

    $session= json_decode($res['response']);

    if ($cart->amz_order_reference_id &&
        $cart->amz_order_reference_id != $session->checkoutSessionId)
    {
      throw new \Exception("Got new checkoutSessionId?");
    }

    $cart->amz_order_reference_id= $session->checkoutSessionId;

    $name= $session->buyer->name;
    $email= $session->buyer->email;

    if (!$cart->name) $cart->name= $name;
    if (!$cart->email) $cart->email= $email;

    // TODO handle $session->shippingAddress->countryCode != 'US'

    $amzn_address= [
      'name' => $session->shippingAddress->name,
      // we put addressLine3 in company, because why not?
      'company' =>  $session->shippingAddress->addressLine3,
      'phone' =>  $session->shippingAddress->phoneNumber ?? '',
      'street1' =>  $session->shippingAddress->addressLine1 ?? '',
      'street2' =>  $session->shippingAddress->addressLine2 ?? '',
      'city' =>  $session->shippingAddress->city,
      'state' =>  $session->shippingAddress->stateOrRegion,
      'zip' =>  $session->shippingAddress->postalCode,
    ];

    $address= $cart->shipping_address($amzn_address);

    $box= $cart->get_shipping_box();
    $weight= $cart->get_shipping_weight();
    $hazmat= $cart->has_hazmat_items();

    // recalculate shipping costs
    list($cost, $method)=
      $shipping->get_shipping_estimate($box, $weight, $hazmat,
                                        $address->as_array());
    $cart->shipping_method= $method ? 'default' : null;
    $cart->shipping= $method ? $cost : null;

    // and then recalculate sales tax
    $cart->recalculateTax($tax);

    $cart->save();

    return $this->view->render($response, 'cart/checkout-amzn.html', [
      'person' => $person,
      'cart' => $cart,
      'amzn' => $session,
    ]);
  }

  public function amznPay(
    Request $request, Response $response,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    // TODO validate

    $client= $this->get_amzn_client();

    $amzn_session_id= $cart->amz_order_reference_id;

    $uri= $request->getUri();
    $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
    $link= $routeContext->getRouteParser()->fullUrlFor(
      $uri,
      'finalize-amzn',
      [],
      [ 'uuid' => $cart->uuid ]
    );

    $data= [
      'webCheckoutDetails' => [
        'checkoutResultReturnUrl' => $link,
      ],
      'paymentDetails' => [
        'paymentIntent' => 'AuthorizeWithCapture',
        'canHandlePendingAuthorization' => false,
        'softDescriptor' => null,
        'chargeAmount' => [
          'amount' => $cart->due(),
          'currencyCode' => 'USD',
        ],
      ],
      'merchantMetadata' => [
        'merchantReferenceId' => $cart->uuid,
        'merchantStoreName' => 'Raw Materials Art Supplies',
        'noteToBuyer' => 'Your order of art supplies',
      ],
    ];

    $res= $client->updateCheckoutSession($amzn_session_id, $data);

    // TODO handle errors

    $session= json_decode($res['response']);

    return $response->withRedirect(
      $session->webCheckoutDetails->amazonPayRedirectUrl
    );
  }
}
