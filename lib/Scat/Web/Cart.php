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

  public function cart(
    Request $request, Response $response,
    \Scat\Service\PayPal $paypal,
    \Scat\Service\AmazonPay $amzn
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    if ($person) {
      if (!$cart->name) $cart->name= $person->name;
      if (!$cart->email) $cart->email= $person->email;
      if ($cart->person_id != $person->id) {
        $cart->person_id= $person->id;
      }
      $cart->save(); /* won't do anything if nothing changed */
    }

    $uri= $request->getUri();
    $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
    $return_link= $routeContext->getRouteParser()->fullUrlFor(
      $uri,
      'checkout-amzn',
      [],
      [ 'uuid' => $cart->uuid ]
    );

    return $this->view->render($response, 'cart/index.html', [
      'person' => $person,
      'cart' => $cart,
      'paypal' => $paypal->getClientId(),
      'amzn' => $amzn->getEnvironment($return_link),
    ]);
  }

  public function cartUpdate(
    Request $request, Response $response,
    \Scat\Service\Shipping $shipping,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    $recalculate= false;

    error_log("processing update: " . json_encode($request->getParams()));

    foreach ($request->getParams() as $key => $value) {
      switch ($key) {
        case 'email':
          // TODO validate
          $cart->email= $value;
          break;
        case 'name':
          $cart->name= $value;
          break;
        case 'address':
          if ($value['city']) {
            error_log("setting new address: " . json_encode($value));
            $cart->updateShippingAddress([
              'name' => $cart->name,
              'street1' => $value['line1'],
              'street2' => $value['line2'] ?? '',
              'city' => $value['city'],
              'state' => $value['state'],
              'zip' => $value['postal_code'],
            ]);
            $recalculate= true;
          }
          break;
        case 'stripe':
          /* ignore, we use this below */
          break;
        default:
          throw new \Exception("Not allowed to change {$key}");
      }
    }

    if ($recalculate) {
      // first we recalculate shipping
      $cart->recalculateShipping($shipping);

      // and then we recalculate sales tax
      $cart->recalculateTax($tax);
    }

    $cart->save();

    $data= $cart->as_array();

    if ($cart->stripe_payment_intent_id) {
      $amount= $cart->due();

      $stripe= new \Stripe\StripeClient([
        'api_key' => $this->config->get('stripe.secret_key'),
        'stripe_version' => "2020-08-27;link_beta=v1",
      ]);

      $payment_intent= $stripe->paymentIntents->retrieve(
        $cart->stripe_payment_intent_id
      );

      if ($payment_intent->status == 'succeeded') {
        throw new \Exception(
          "Stripe payment intent {$payment_intent->id} already succeeeded?!"
        );
      }

      $customer_details= [
        'email' => $cart->email,
        'name' => $cart->name,
        'metadata' => [
          "person_id" => $cart->person_id,
        ],
      ];

      if ($cart->shipping_address_id > 1) {
        $address= $cart->shipping_address();

        $customer_details['shipping']= [
          'name' => $address->name,
          'phone' => $address->phone,
          'address' => [
            'line1' => $address->street1,
            'line2' => $address->street2,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->zip,
            'country' => 'US',
          ],
        ];
      }

      $intent_options= [
        'amount' => $amount * 100,
      ];

      if ($payment_intent->customer) {
        $stripe->customers->update(
          $payment_intent->customer,
          $customer_details
        );
      } else {
        $customer= $stripe->customers->create($customer_details);
        $intent_options['customer']= $customer->id;
      }

      if ($customer_details['shipping']) {
        //$intent_options['shipping']= $customer_details['shipping'];
      }
      $stripe->paymentIntents->update(
        $cart->stripe_payment_intent_id,
        $intent_options
      );
    }

    if ($request->getParam('stripe')) {
      $data['cart']=
        $this->view->fetchBlock('cart/checkout.html', 'cart', [
          'person' => $person,
          'cart' => $cart,
        ]);
      $data['shipping_options']=
        $this->view->fetchBlock('cart/checkout.html', 'shipping_options', [
          'person' => $person,
          'cart' => $cart,
        ]);
    }

    return $response->withJson($data);
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

  public function checkout(
    Request $request, Response $response,
    \Scat\Service\Shipping $shipping,
    \Scat\Service\PayPal $paypal,
    \Scat\Service\Stripe $stripe,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    $paymentIntent= $stripe->getPaymentIntent($cart);

    $cart->stripe_payment_intent_id= $paymentIntent->id;
    $cart->save();

    return $this->view->render($response, 'cart/checkout.html', [
      'person' => $person,
      'cart' => $cart,
      'stripe' => [
        'key' => $stripe->getPublicKey(),
        'payment_intent' => $paymentIntent,
      ],
      'paypal' => $paypal->getClientId(),
    ]);
  }

  public function amznCheckout(
    Request $request, Response $response,
    \Scat\Service\Shipping $shipping,
    \Scat\Service\AmazonPay $amzn,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    $amzn_session_id= $request->getParam('amazonCheckoutSessionId');

    $session= $amzn->getCheckoutSession($amzn_session_id);

    if ($cart->amz_order_reference_id &&
        $cart->amz_order_reference_id != $session->checkoutSessionId)
    {
      error_log("Got new checkoutSessionId, hope that's okay.");
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

    $cart->updateShippingAddress($amzn_address);

    // recalculate shipping
    $cart->recalculateShipping($shipping);

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
    \Scat\Service\AmazonPay $amzn,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    // TODO validate

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
        'paymentIntent' => 'Authorize',
        'canHandlePendingAuthorization' => false,
        'softDescriptor' => null,
        'chargeAmount' => [
          'amount' => $cart->due(),
          'currencyCode' => 'USD',
        ],
      ],
      'merchantMetadata' => [
        'merchantReferenceId' => $cart->id,
        'merchantStoreName' => 'Raw Materials Art Supplies',
        'noteToBuyer' => 'Your order of art supplies',
        'customInformation' => $cart->uuid,
      ],
    ];

    $session= $amzn->updateCheckoutSession($amzn_session_id, $data);

    // TODO handle errors
    if (!isset($session->statusDetails)) {
      throw new \Exception($session->message);
    }

    return $response->withRedirect(
      $session->webCheckoutDetails->amazonPayRedirectUrl
    );
  }

  public function amznFinalize(
    Request $request, Response $response,
    \Scat\Service\AmazonPay $amzn,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    // TODO validate

    $amzn_session_id= $cart->amz_order_reference_id;

    $data= [
      'chargeAmount' => [
        'amount' => $cart->due(),
        'currencyCode' => 'USD',
      ],
    ];

    $session= $amzn->completeCheckoutSession($amzn_session_id, $data);

    if (!isset($session->statusDetails)) {
      throw new \Exception($session->message);
    }

    $cart->addPayment('amazon', $cart->due(), false, $session);

    $cart->save();

    if ($cart->status != 'paid') {
      throw new \Exception("Not completely paid!");
    }

    $uri= $request->getUri();
    $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
    $link= $routeContext->getRouteParser()->fullUrlFor(
      $uri,
      'sale-thanks',
      [ 'uuid' => $cart->uuid ]
    );

    /* Set cart id to -1 so cookie will get unset */
    $cart->id= -1;

    return $response->withRedirect($link);
  }

  public function stripeCheckout(
    Request $request, Response $response,
    \Scat\Service\Shipping $shipping,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    $stripe= new \Stripe\StripeClient([
      'api_key' => $this->config->get('stripe.secret_key'),
      'stripe_version' => "2020-08-27;link_beta=v1",
    ]);

    $paymentIntent= $stripe->paymentIntents->create([
      'payment_method_types' => [
        'link',
        'card'
      ],
      'metadata' => [
        "sale_id" => $cart->id,
        "sale_uuid" => $cart->uuid,
      ],
      'amount' => $cart->due() * 100,
      'currency' => 'usd',
    ]);

    $cart->stripe_payment_intent_id= $paymentIntent->id;
    $cart->save();

    return $this->view->render($response, 'cart/checkout-stripe.html', [
      'person' => $person,
      'cart' => $cart,
      'stripe' => [
        'key' => $this->config->get('stripe.key'),
        'payment_intent' => $paymentIntent,
      ],
    ]);
  }

  public function setShipped(
    Request $request,
    Response $response,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');

    $cart->shipping_address_id= 0;
    $cart->shipping_method= null;
    $cart->shipping= 0;
    $cart->shipping_tax= 0;

    // and then recalculate (clear) sales tax
    $cart->recalculateTax($tax);

    $cart->save();

    return $response->withRedirect('/cart/checkout');
  }

  public function setCurbsidePickup(
    Request $request,
    Response $response,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');

    $cart->shipping_address_id= 1;
    $cart->shipping_method= 'default';
    $cart->shipping= 0;
    $cart->shipping_tax= 0;

    // and then recalculate sales tax
    $cart->recalculateTax($tax);

    $cart->save();

    return $response->withRedirect('/cart/checkout');
  }

  public function stripeFinalize(
    Request $request, Response $response,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    // TODO validate

    if ($cart->status != 'paid') {
      throw new \Exception("Not completely paid!");
    }

    $uri= $request->getUri();
    $routeContext= \Slim\Routing\RouteContext::fromRequest($request);
    $link= $routeContext->getRouteParser()->fullUrlFor(
      $uri,
      'sale-thanks',
      [ 'uuid' => $cart->uuid ]
    );

    /* Set cart id to -1 so cookie will get unset */
    $cart->id= -1;

    return $response->withRedirect($link);
  }

  public function handleStripeWebhook(
    Request $request, Response $response,
    \Scat\Service\Cart $carts
  ) {
    \Stripe\Stripe::setApiKey($this->config->get('stripe.secret_key'));

    try {
      $event= \Stripe\Webhook::constructEvent(
        $request->getBody(),
        $request->getHeaderLine('Stripe-Signature'),
        $this->config->get('stripe.webhook_secret')
      );
    } catch(\UnexpectedValueException $e) {
      // TODO should be 400 error
      throw new \Exception("Invalid payload");
    } catch(\Stripe\Exception\SignatureVerificationException $e) {
      // TODO should be 400 error
      throw new \Exception("Signature exception");
    }

    // Handle the event
    switch ($event->type) {
      case 'payment_intent.succeeded':
        $paymentIntent= $event->data->object; // contains a StripePaymentIntent
        $uuid= $paymentIntent->charges->data[0]->metadata->sale_uuid;
        if (!$uuid) {
          error_log("No uuid on payment_intent, probably a gift card");
          break;
        }

        $cart= $carts->findByUuid($uuid);
        if (!$cart) {
          throw new \Slim\Exception\HttpNotFoundException($request);
        }

        $payment_intent_id= $cart->stripe_payment_intent_id;

	// if we already have it, don't do it again!
        $has= $cart->payments()
                    ->where_raw(
                      'data->"$.payment_intent_id" = ?', [ $payment_intent_id ])
                    ->find_one();
	if ($has) {
	  error_log("Already processed Stripe payment $payment_intent_id\n");
	  return $response->withJson([ 'message' => 'Already processed.' ]);
	}

        $stripe= new \Stripe\StripeClient([
          'api_key' => $this->config->get('stripe.secret_key'),
          'stripe_version' => "2020-08-27;link_beta=v1",
        ]);

        $payment_intent=
          $stripe->paymentIntents->retrieve($cart->stripe_payment_intent_id, []);

        if ($payment_intent->status != 'succeeded') {
          throw new \Exception("Can only handle successful payment attempts here.");
        }

        foreach ($payment_intent->charges->data as $charge) {
          if ($charge->payment_method_details->type == 'afterpay_clearpay') {
            $cc_brand= 'AfterPay';
            $cc_last4= '';
          } if ($charge->payment_method_details->type == 'link') {
            $cc_brand= 'Link';
            $cc_last4= '';
          } else {
            $cc_brand= ucwords($charge->payment_method_details->card->brand);
            $cc_last4= $charge->payment_method_details->card->last4;
          }

          $data= [
            'payment_intent_id' => $payment_intent_id,
            'charge_id' => $charge->id,
            'cc_brand' => $cc_brand,
            'cc_last4' => $cc_last4,
          ];

          $cart->addPayment('credit', $charge->amount / 100, true, $data);
        }

        $cart->save();

        if ($cart->status != 'paid') {
//          throw new \Exception("Not completely paid!");
        }

        break;

      case 'payment_intent.payment_failed':
        /* Don't do anything with these yet. */

      default:
        error_log("Ignoring {$event->type} from Stripe");
    }

    return $response->withJson([ 'message' => 'Success!' ]);
  }

  public function paypalCheckout(
    Request $request, Response $response,
    \Scat\Service\PayPal $paypal
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    return $this->view->render($response, 'cart/checkout-paypal.html', [
      'paypal' => [
        'client_id' => $paypal->getClientId(),
      ],
      'person' => $person,
      'cart' => $cart,
    ]);
  }

  public function paypalOrder(
    Request $request, Response $response,
    \Scat\Service\Shipping $shipping,
    \Scat\Service\PayPal $paypal,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    /* Less detail to the amount if order is partially paid already. */
    if ($cart->total_paid()) {
      $amount= [
        'currency_code' => 'USD',
        'value' => $cart->due(),
      ];
    } else {
      $amount= [
        'currency_code' => 'USD',
        'value' => $cart->total(),
        'breakdown' => [
          'item_total' => [
            'currency_code' => 'USD',
            // TODO need to use Decimal here?
            'value' => $cart->subtotal() + cart->shipping,
          ],
          'tax_total' => [
            'currency_code' => 'USD',
          ],
        ]
      ];
    }

    $address= $cart->shipping_address();

    $shipping= [
      'name' => [
        'full_name' => $address->name ?: $address->company,
      ],
      'address' => [
        'address_line_1' => $address->street1,
        'address_line_2' => $address->street2,
        'admin_area_2' => $address->city,
        'admin_area_1' => $address->state,
        'postal_code' => $address->zip,
        'country_code' => 'US',
      ],
    ];

    $details= [
      'intent' => 'CAPTURE',
      'application_context' => [
        'shipping_preference' => 'SET_PROVIDED_ADDRESS',
        'user_action' => 'PAY_NOW',
      ],
      'purchase_units' => [
        [
          'reference_id' => $cart->uuid,
          'custom_id' => $cart->uuid,
          'amount' => $amount,
          'items' => [],
          'shipping' => $shipping,
        ]
      ],
    ];

    $order= null;

    try {
      if ($cart->paypal_order_id) {
        $order= $paypal->getOrder($cart->paypal_order_id);
      }
    } catch (\PayPalHttp\HttpException $e) {
      error_log("HttpException {$e->statusCode}");
      $cart->paypal_order_id= null;
    }

    if ($order) {
      if ($order->status == 'COMPLETED') {
        throw new \Exception("Payment already completed.");
      }

      $patch = [
        [
          'op' => 'replace',
          'path' => "/purchase_units/@reference_id=='{$cart->uuid}'/amount",
          'value' => $amount,
        ],
        [
          'op' => 'replace',
          'path' => "/purchase_units/@reference_id=='{$cart->uuid}'/shipping/name",
          'value' => $shipping['name'],
        ],
        [
          'op' => 'replace',
          'path' => "/purchase_units/@reference_id=='{$cart->uuid}'/shipping/address",
          'value' => $shipping['address'],
        ]
      ];

      $paypal->updateOrder($cart->paypal_order_id, $patch);
    } else {
      $order= $paypal->createOrder($details);
      $cart->paypal_order_id= $order->id;
    }

    $cart->save();

    return $response->withJson($order);
  }

  public function paypalUpdate(
    Request $request, Response $response,
    \Scat\Service\PayPal $paypal,
    \Scat\Service\Shipping $shipping,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    error_log("incoming: " . json_encode($request->getParams()));

    $order= $paypal->getOrder($cart->paypal_order_id);

    error_log("order details: " . json_encode($order));

    $recalculate= false;

    if (isset($order->payer)) {
      $cart->name= trim($order->payer->name->given_name . ' ' . $order->payer->name->surname);
    }

    if (isset($order->purchase_units[0]->shipping)) {
      $details= $order->purchase_units[0]->shipping;
      $cart->updateShippingAddress([
        'name' => $details->name->full_name ?? '',
        'street1' => $details->address->address_line_1 ?? '',
        'street2' => $details->address->address_line_2 ?? '',
        'city' => $details->address->admin_area_2 ?? '',
        'state' => $details->address->admin_area_1 ?? '',
        'zip' => $details->address->postal_code ?? '',
      ]);
      $recalculate= true;
    }

    if ($recalculate) {
      $cart->recalculateShipping($shipping);

      // and then recalculate sales tax
      $cart->recalculateTax($tax);
    }

    $cart->save();

    $data= $cart->as_array();

    if ($cart->total_paid()) {
      $amount= [
        'currency_code' => 'USD',
        'value' => $cart->due(),
      ];
    } else {
      $amount= [
        'currency_code' => 'USD',
        'value' => $cart->total(),
        'breakdown' => [
          'item_total' => [
            'currency_code' => 'USD',
            // TODO need to use Decimal here?
            'value' => $cart->subtotal() + cart->shipping,
          ],
          'tax_total' => [
            'currency_code' => 'USD',
            'value' => $cart->tax(),
          ],
        ]
      ];
    }

    $patch= [
      [
        'op' => 'replace',
        'path' => "/purchase_units/@reference_id=='{$cart->uuid}'/amount",
        'value' => $amount,
      ]
    ];

    $paypal->updateOrder($cart->paypal_order_id, $patch);

    return $response->withJson($data);
  }

  public function paypalSetPickup(
    Request $request, Response $response,
    \Scat\Service\PayPal $paypal,
    \Scat\Service\Shipping $shipping,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    // TODO should be part of login/guest process
    $cart->name= $request->getParam('name');
    $cart->email= $request->getParam('email');

    $cart->shipping_address_id= 1;
    $cart->shipping_method= 'default';
    $cart->shipping= 0;
    $cart->shipping_tax= 0;

    // and then recalculate sales tax
    $cart->recalculateTax($tax);

    $cart->save();

    return $response->withRedirect('/cart/pay/paypal');
  }

  public function paypalSetAddress(
    Request $request, Response $response,
    \Scat\Service\PayPal $paypal,
    \Scat\Service\Shipping $shipping,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    // TODO should be part of login/guest process
    $cart->name= $request->getParam('name');
    $cart->email= $request->getParam('email');

    $cart->updateShippingAddress([
      'name' => $request->getParam('shipping_name'),
      'company' => $request->getParam('company'),
      'phone' => $request->getParam('phone'),
      'street1' => $request->getParam('street1'),
      'street2' => $request->getParam('street2'),
      'city' => $request->getParam('city'),
      'state' => $request->getParam('state'),
      'zip' => $request->getParam('zip'),
    ]);

    $cart->recalculateShipping($shipping);

    // and then recalculate sales tax
    $cart->recalculateTax($tax);

    $cart->save();

    return $response->withRedirect('/cart/pay/paypal');
  }

  public function paypalPay(
    Request $request, Response $response,
    \Scat\Service\PayPal $paypal,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    // TODO validate
    return $this->view->render($response, 'cart/pay-paypal.html', [
      'person' => $person,
      'cart' => $cart,
      'paypal' => $paypal->getClientId(),
    ]);
  }

  public function paypalFinalize(
    Request $request, Response $response,
    \Scat\Service\PayPal $paypal,
    \Scat\Service\Tax $tax
  ) {
    $cart= $request->getAttribute('cart');
    $person= $this->auth->get_person_details($request);

    // Save comment, not fatal if it doesn't work
    if (($comment= $request->getParam('comment'))) {
      $note= $cart->notes()->create([
        'sale_id' => $cart->id,
        'person_id' => $person->id,
        'content' => $comment,
      ]);
      try {
        $note->save();
      } catch (\Exception $e) {
        error_log("Failed to save comment for {$cart->uuid}: {$comment}");
      }
    }

    $uuid= $cart->uuid;
    $order_id= $request->getParam('order_id');

    // TODO validate that $order_id = $cart->paypal_order_id

    return $this->paypalFinalizeCart(
      $request,
      $response,
      $paypal,
      $tax,
      $cart
    );
  }

  public function paypalFinalizeCart(
    Request $request, Response $response,
    \Scat\Service\PayPal $paypal,
    \Scat\Service\Tax $tax,
    \Scat\Model\Cart $cart
  ) {
    $order_id= $cart->paypal_order_id;

    // TODO validate
    error_log("Finalizing PayPal {$order_id} on {$cart->uuid}");

    // if we already have it, don't do it again!
    $has= $cart->payments()
                ->where_raw(
                  'data->"$.id" = ?', [ $order_id ])
                ->find_one();
    if ($has) {
      error_log("Already processed PayPal payment $order_id\n");
      return $response->withJson([ 'message' => 'Already processed.' ]);
    }

    $order= $paypal->getOrder($order_id);

    $amount= $order->purchase_units[0]->amount->value;

    $cart->addPayment('paypal', $amount, false, $order);

    $cart->save();

end:
    if ($cart->status != 'paid') {
      throw new \Exception("Not completely paid!");
    }

    /* Set cart id to -1 so cookie will get unset */
    $cart->id= -1;

    return $response->withJson([ 'message' => 'Processed' ] );
  }

  public function handlePayPalWebhook(
    Request $request, Response $response,
    \Scat\Service\PayPal $paypal,
    \Scat\Service\Tax $tax,
    \Scat\Service\Cart $carts
  ) {
    $webhook_id= $paypal->getWebhookId();

    // adapted from https://stackoverflow.com/a/62870569
    if ($webhook_id) {
      $headers= $request->getHeaders();
      $body= $request->getBody();

      $data= join('|', [
		  $headers['Paypal-Transmission-Id'][0],
		  $headers['Paypal-Transmission-Time'][0],
		  $webhook_id,
		  crc32($body) ]);

      $cert= file_get_contents($headers['Paypal-Cert-Url'][0]);
      $pubkey= openssl_pkey_get_public($cert);

      $sig= base64_decode($headers['Paypal-Transmission-Sig'][0]);

      $res= openssl_verify($data, $sig, $pubkey, 'sha256WithRSAEncryption');

      if ($res == 0) {
        throw new \Exception("Webhook signature validation failed.");
      } elseif ($res < 0) {
        throw new \Exception("Error validating signature: " . openssl_error_string());
      }
    }

    $event_type= $request->getParam('event_type');
    $resource= $request->getParam('resource');

    // Handle the event
    switch ($event_type) {
      case 'PAYMENT.CAPTURE.COMPLETED':
        $uuid= $resource['custom_id'];
        if (!$uuid) {
          error_log("No uuid on resource, dunno what this is");
          error_log(json_encode($resource));
          break;
        }

        $cart= $carts->findByUuid($uuid);
        if (!$cart) {
          throw new \Slim\Exception\HttpNotFoundException($request);
        }

        return $this->paypalFinalizeCart(
          $request,
          $response,
          $paypal,
          $tax,
          $cart
        );

      default:
        error_log("Ignoring {$event_type} from PayPal");
        throw new \Slim\Exception\HttpNotFoundException($request);
    }

    return $response->withJson([ 'message' => 'Success!' ]);
  }
}
