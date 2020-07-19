<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class Tax {
  private $tax;

  public function __construct(\Scat\Service\Tax $tax) {
    $this->tax= $tax;
  }

  function ping(Request $request, Response $response) {
    return $response->withJson($this->tax->ping());
  }

  function getTICs(Request $request, Response $response) {
    return $response->withJson($this->tax->getTICs());
  }

  function test(Request $request, Response $response) {
    $data= [
      'customerID' => 0,
      'cartID' => 'testing-things-here',
      'deliveredBySeller' => false,
      'origin' => [
        'Address1' => '645 S Los Angeles St',
        'Address2' => '',
        'City' => 'Los Angeles',
        'State' => 'CA',
        'Zip5' => '90014',
        'Zip4' => '',
      ],
      'destination' => [
        'Address1' => '645 S Los Angeles St',
        'Address2' => '',
        'City' => 'Los Angeles',
        'State' => 'CA',
        'Zip5' => '90014',
        'Zip4' => '',
      ],
      'cartItems' => [
        [
          'Index' => 0,
          'ItemID' => 'AA5502',
          'TIC' => '00000',
          'Price' => '3.00',
          'Qty' => 2,
        ],
      ]
    ];

    return $response->withJson($this->tax->lookup($data));
  }
}
