<?php
namespace Scat\Controller;

use \Psr\Container\ContainerInterface;
use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Respect\Validation\Validator as v;
use \DavidePastore\Slim\Validation\Validation as Validation;

class Quickbooks {
  protected $container;
  protected $qb;

  protected $config= [
    'auth_mode' => 'oauth2',
    'ClientID' => QB_CLIENT_ID,
    'ClientSecret' => QB_CLIENT_SECRET,
    'RedirectURI' => QB_REDIRECT_URI,
    'baseUrl' => QB_BASE_URL,
    'scope' => QB_SCOPE,
  ];

  private $account_list= [
    [ 'Cash Over/Short', 'Other Expense', 'OtherMiscellaneousExpense' ],
    [ 'Undeposited Funds', 'Other Current Asset', 'UndepositedFunds' ],
    [ 'Accounts Receivable', 'Accounts Receivable', 'AccountsReceivable' ],
    [ 'Cash Drawer', 'Other Current Asset', 'OtherCurrentAssets' ],
    [ 'CardConnect Clearing Account', 'Other Current Asset', 'OtherCurrentAssets' ],
    [ 'Square Clearing Account', 'Other Current Asset', 'OtherCurrentAssets' ],
    [ 'Stripe Clearing Account', 'Other Current Asset', 'OtherCurrentAssets' ],
    [ 'Amazon Clearing Account', 'Other Current Asset', 'OtherCurrentAssets' ],
    [ 'EventBrite Clearing Account', 'Other Current Asset', 'OtherCurrentAssets' ],
    [ 'Unclaimed Gift Certificates', 'Other Current Liability', 'OtherCurrentLiabilities' ],
    [ 'Undeposited Funds', 'Other Current Asset', 'UndepositedFunds' ],
    [ 'PayPal', 'Bank', 'Checking' ],
    [ 'Discounts Given', 'Income', 'DiscountsRefundsGiven' ],
    [ 'Shrinkage', 'Expense', 'SuppliesMaterials' ],
    [ 'Donation', 'Expense', 'CharitableContributions' ],
    [ 'Store Supplies', 'Expense', 'SuppliesMaterials' ],
    [ 'Art Supplies', 'Other Current Asset', 'Inventory' ],
    [ 'Sales Tax Payable', 'Other Current Liability', 'SalesTaxPayable' ],
    [ 'Sales of Art Supplies', 'Income', 'SalesOfProductIncome' ],
    [ 'Sales of Art', 'Income', 'SalesOfProductIncome' ],
    [ 'Class Fees', 'Income', 'ServiceFeeIncome' ],
    [ 'Delivery/Shipping & Handling', 'Income', 'ServiceFeeIncome' ],
    [ 'Cost of Goods Sold', 'Cost of Goods Sold', 'SuppliesMaterialsCogs' ],
  ];

  public function __construct(ContainerInterface $container) {
    $this->container= $container;
  }

  protected function refreshToken() {
    // TODO base config should be elsewhere
    $config= $this->config;

    $accessToken= \Scat\Model\Config::getValue('qb.accessToken');
    $refreshToken= \Scat\Model\Config::getValue('qb.refreshToken');
    $realmId= \Scat\Model\Config::getValue('qb.realmId');

    if ($accessToken && $refreshToken && $realmId) {
      $config['accessTokenKey']= $accessToken;
      $config['refreshTokenKey']= $refreshToken;
      $config['QBORealmID']= $realmId;
    }

    $this->qb=
      \QuickBooksOnline\API\DataService\DataService::Configure($config);
    $helper= $this->qb->getOAuth2LoginHelper();

    if ($refreshToken) {
      $token= $helper->refreshToken();
      \Scat\Model\Config::setValue('qb.accessToken',
                                   $token->getAccessToken());
      \Scat\Model\Config::setValue('qb.refreshToken',
                                   $token->getRefreshToken());
      $this->qb->updateOAuth2Token($token);

      return true;
    }

    return false;
  }

  public function connect(Response $response, $code, $realmId) {
    $qb=
      \QuickBooksOnline\API\DataService\DataService::Configure($this->config);
    $helper= $qb->getOAuth2LoginHelper();

    $token= $helper->exchangeAuthorizationCodeForToken($code, $realmId);
    $qb->updateOAuth2Token($token);

    \Scat\Model\Config::setValue('qb.accessToken', $token->getAccessToken());
    \Scat\Model\Config::setValue('qb.refreshToken', $token->getRefreshToken());
    \Scat\Model\Config::setValue('qb.realmId', $realmId);

    return $response->withRedirect('/quickbooks');
  }

  public function home(Request $request, Response $response) {
    if ($request->getParam('code')) {
      return $this->connect($response,
                            $request->getParam('code'),
                            $request->getParam('realmId'));
    }

    try {
      $this->connected= $this->refreshToken();
    } catch (\Exception $e) {
      $errors[]= $e->getMessage();
    }

    return $this->container->get('view')->render($response, "quickbooks/index.html", [
      'qb' => $this->qb,
      'connected' => $this->connected,
      'last_synced_payment' => $this->getLastSyncedPayment(),
      'last_synced_sale' => $this->getLastSyncedSale(),
      'errors' => $errors,
    ]);
  }

  protected function getLastSyncedPayment() {
    return \Model::factory('Payment')
            ->where_gt('amount', '0')
            ->where('qb_je_id', '')
            ->where_gte('processed', '2020-01-01')
            ->min('processed');
  }

  protected function getLastSyncedSale() {
    return \Model::factory('Txn')
            ->where('type', 'customer')
            ->where('qb_je_id', '')
            ->where_gte('paid', '2020-01-01')
            ->min('paid');
  }

  public function disconnect(Request $request, Response $response) {
    \Scat\Model\Config::forgetValue('qb.accessToken');
    \Scat\Model\Config::forgetValue('qb.refreshToken');
    \Scat\Model\Config::forgetValue('qb.realmId');
    return $response->withRedirect('/quickbooks');
  }

  public function verifyAccounts(Request $request, Response $response) {
    if (!$this->refreshToken()) {
      throw new \Exception("Unable to refresh OAuth2 token");
    }

    $accounts= [];
    foreach ($this->account_list as $i => $account) {
      try {
        $this->getAccountByName($account[0]);
      } catch (\Exception $e) {
        $accounts[$i]= $account[0];
      }
    }

    return $this->container->get('view')->render($response, "quickbooks/accounts.html", [
      'accounts' => $accounts
    ]);
  }

  public function createAccount(Request $request, Response $response) {
    $id= $request->getParam('id');
    if (!$id)
      throw new \Slim\Exception\NotFoundException($request, $response);

    if (!$this->refreshToken()) {
      throw new \Exception("Unable to refresh OAuth2 token");
    }

    $obj= \QuickBooksOnline\API\Facades\Account::create([
      'Name' => $this->account_list[$id][0],
      'AccountType' => $this->account_list[$id][1],
      'AccountSubType' => $this->account_list[$id][2]
    ]);

    $res= $this->qb->Add($obj);
    $error= $this->qb->getLastError();
    if ($error) {
      throw new \Exception($error->getResponseBody());
    }

    return $response->withJson([ 'message' => 'Success!' ]);
  }

  public function sync(Request $request, Response $response) {
    if ($request->getAttribute('has_errors')) {
      return $response->withJson([
        'error' => "Validation failed.",
        'validation_errors' => $request->getAttribute('errors')
      ]);
    }

    if (!$this->refreshToken()) {
      throw new \Exception("Unable to refresh OAuth2 token");
    }

    $from= $request->getParam('from');
    $date= $request->getParam('date');

    switch ($from) {
    case 'payments':
      $this->syncPayments($date);
      $latest= $this->getLastSyncedPayment();
      break;
    case 'sales':
      $this->syncSales($date);
      $latest= $this->getLastSyncedSale();
      break;
    }

    $latest= (new \DateTime($latest))->format('Y-m-d');

    return $response->withJson([ 'latest' => $latest ]);
  }

  function syncSales($date) {
    $account= [
      // assets
      'receivable' => 'Accounts Receivable',
      'inventory'  => 'Art Supplies',
      // liabilities
      'gift'       => 'Unclaimed Gift Certificates',
      'salestax'   => 'Sales Tax Payable',
      // sales
      'art'        => 'Sales of Art',
      'supplies'   => 'Sales of Art Supplies',
      'class'      => 'Class Fees',
      'freight'    => 'Delivery/Shipping & Handling',
      // cost of sales
      'costofgoods'=> 'Cost of Goods Sold',
      'loyalty'    => 'Discounts Given',
      // shrink
      'shrink'     => 'Shrinkage',
    ];

    $date= (new \DateTime($date))->format('Y-m-d');

    error_log("Finding transactions on $date");

    $txns= \Model::factory('Txn')
            ->where_raw("qb_je_id = '' AND
                         ((type = 'correction' AND
                           created BETWEEN ? AND ? + INTERVAL 1 DAY) OR
                          (type = 'customer' AND
                           paid BETWEEN ? AND ? + INTERVAL 1 DAY))",
                          [ $date, $date, $date, $date ])
            ->find_many();

    $success= [];
    foreach ($txns as $txn) {
      error_log("Syncing {$txn->id}");
      switch ($txn->type) {
      case 'correction':
        if ($txn->total() == 0)
          continue 2;

        $memo= "{$txn->id}: Correction {$txn->formatted_number()}";

        $data= [
          "DocNumber" => 'S' . $txn->id,
          "TxnDate" => (new \DateTime($txn->created))->format('Y-m-d'),
          'Line' => [
            $this->generateLine($memo, 'Shrinkage',    bcmul($txn->total(),-1)),
            $this->generateLine($memo, 'Art Supplies', $txn->total()),
          ]
        ];

        break;

      case 'customer':
        $memo= "{$txn->id}: Invoice {$txn->formatted_number()}";

        $data= [
          "DocNumber" => 'S' . $txn->id,
          "TxnDate" => (new \DateTime($txn->created))->format('Y-m-d'),
          'Line' => [
            // receivable
            $this->generateLine($memo, 'Accounts Receivable', $txn->total()),
          ]
        ];

        if ($txn->tax() != 0) {
          $data['Line'][]= $this->generateLine($memo, 'Sales Tax Payable',
                                 bcmul($txn->tax(), -1));
        }

        $sales= [];
        $costs= $total= "0.00";

        foreach ($txn->items() as $line) {
          $item= $line->item();

          $category= 'supplies';
          if (preg_match('/^ZZ-frame/i', $item->code)) {
            $category= 'framing';
          } elseif (preg_match('/^ZZ-(print|scan)/i', $item->code)) {
            $category= 'printing';
          } elseif (preg_match('/^ZZ-art/i', $item->code)) {
            $category= 'art';
          } elseif (preg_match('/^ZZ-online/i', $item->code)) {
            $category= 'online';
          } elseif (preg_match('/^ZZ-class/i', $item->code)) {
            $category= 'class';
          } elseif (preg_match('/^ZZ-gift/i', $item->code)) {
            $category= 'gift';
          } elseif (preg_match('/^ZZ-loyalty/i', $item->code)) {
            $category= 'loyalty';
          } elseif (preg_match('/^ZZ-shipping/i', $item->code)) {
            $category= 'freight';
          }

          $sales[$category]= bcadd($sales[$category], $line->ext_price());
          $total= bcadd($total, $line->ext_price());
          $costs= bcadd($costs, $line->cost_of_goods());
        }

        // $txn->subtotal has polarity opposite what we've done for total
        if ($total != bcmul($txn->subtotal(), -1)) {
          $sales['supplies']= bcsub($sales['supplies'],
                                    bcadd($txn->subtotal(), $total));
        }

        foreach ($sales as $category => $amount) {
          // sale
          $data['Line'][]= $this->generateLine($memo, $account[$category], $amount);
        }

        if ($costs != "0.00") {
          $data['Line'][]= $this->generateLine($memo, $account['inventory'],   $costs);
          $data['Line'][]= $this->generateLine($memo, $account['costofgoods'], bcmul($costs, -1));
        }

        break;

      default:
        throw new \Exception("ERROR: Unable to handle transaction type '$sale[type]'");
      }

      $data['Line']= array_values(array_filter($data['Line']));

      if (count($data['Line']) == 0) {
        error_log("Transaction {$txn->id} was a wash, skipping");
        $txn->qb_je_id= 'skipped';
        $txn->save();
        continue;
      }

      $je= \QuickBooksOnline\API\Facades\JournalEntry::create($data);

      $res= $this->qb->Add($je);
      $error = $this->qb->getLastError();
      if ($error) {
        throw new \Exception($error->getResponseBody());
      }
      else {
        $txn->qb_je_id= $res->Id;
        $txn->save();
        $success[]= $res->Id;
      }
    }
  }

  function syncPayments($date) {
    $accts= [
      'drawer' => [
        'cash' =>       ['Cash Drawer', 'Cash Over/Short'],
        'withdrawal' => ['Cash Drawer', 'Undeposited Funds'],
      ],
      'customer' => [
        'cash' =>       ['Cash Drawer', 'Accounts Receivable'],
        'change' =>     ['Cash Drawer', 'Accounts Receivable'],
        'credit' =>     ['CardConnect Clearing Account', 'Accounts Receivable'],
        'square' =>     ['Square Clearing Account', 'Accounts Receivable'],
        'stripe' =>     ['Stripe Clearing Account', 'Accounts Receivable'],
        'amazon' =>     ['Amazon Clearing Account', 'Accounts Receivable'],
        'eventbrite' => ['EventBrite Clearing Account', 'Accounts Receivable'],
        'gift' =>       ['Unclaimed Gift Certificates', 'Accounts Receivable'],
        'check' =>      ['Undeposited Funds', 'Accounts Receivable'],
        'paypal'=>      ['PayPal', 'Accounts Receivable'],
        'discount'=>    ['Discounts Given', 'Accounts Receivable'],
        'bad'=>         ['Shrinkage', 'Accounts Receivable'],
        'donation'=>    ['Donation', 'Accounts Receivable'],
        'internal'=>    ['Store Supplies', 'Accounts Receivable'],
      ]
    ];

    $date= (new \DateTime($date))->format('Y-m-d');

    $payments= \Model::factory('Payment')
                ->where_raw("processed BETWEEN ? and ? + INTERVAL 1 DAY",
                            [ $date, $date ])
                ->where_not_equal('amount', '0.00')
                ->where_equal('qb_je_id', '')
                ->find_many();

    foreach($payments as $pay) {
      $txn= $pay->txn();
      $method= $pay->method;

      list($debit, $credit)= $accts[$txn->type][$method];

      if (!$debit) {
        throw new \Exception("ERROR: Can't handle '$method' for '{$txn->type}' payment");
      }

      switch ($txn->type) {
      case 'drawer':
        $memo= "Till Count " . $txn->formatted_number();
        break;

      case 'customer':
        $memo= "Payment for invoice " . $txn->formatted_number();
        break;

      default:
        die("ERROR: Don't know how to handle '{$txn->type}' payment");
      }

      // create blank entry
      $data= [
        "DocNumber" => 'P' . $pay->id,
        "TxnDate" => (new \DateTime($pay->processed))->format('Y-m-d'),
        'Line' => [
          [
            "Description" => $memo,
            "Amount" => $pay->amount < 0 ? substr($pay->amount, 1) : $pay->amount,
            "DetailType" => "JournalEntryLineDetail",
            "JournalEntryLineDetail" => [
              "PostingType" => $pay->amount < 0 ? "Credit" : "Debit",
              "AccountRef" => $this->getAccountByName($debit),
              "Entity" => [
                "EntityRef" => $this->getCustomerByName("Retail Customer"),
                "Type" => 'Customer'
              ],
            ]
          ],
          [
            "Description" => $memo,
            "Amount" => $pay->amount < 0 ? substr($pay->amount, 1) : $pay->amount,
            "DetailType" => "JournalEntryLineDetail",
            "JournalEntryLineDetail" => [
              "PostingType" => $pay->amount < 0 ? "Debit" : "Credit",
              "AccountRef" => $this->getAccountByName($credit),
              "Entity" => [
                "EntityRef" => $this->getCustomerByName("Retail Customer"),
                "Type" => 'Customer'
              ],
            ]
          ],
        ]
      ];

      $je= \QuickBooksOnline\API\Facades\JournalEntry::create($data);

      $res= $this->qb->Add($je);
      $error= $this->qb->getLastError();
      if ($error) {
        throw new \Exception($error->getResponseBody());
      }
      else {
        $pay->qb_je_id= $res->Id;
        $pay->save();
      }
    }
  }

  // memoization is fun
  protected function getAccountByName($name) {
    static $cache= [];

    $name= addslashes($name);

    if ($cache[$name]) {
      return $cache[$name];
    }

    $res= $this->qb->Query("SELECT * FROM Account WHERE name = '$name'");
    if (!$res) {
      throw new \Exception("Not able to find account '$name'");
    }

    return ($cache[$name] = [
      'value' => $res[0]->Id,
      'name' => $res[0]->Name,
    ]);
  }

  protected function getCustomerByName($name, $create= true) {
    static $cache= [];

    $name= addslashes($name);

    if ($cache[$name]) {
      return $cache[$name];
    }

    $res= $this->qb->Query("SELECT * FROM Customer WHERE DisplayName = '$name'");
    if (!$res) {
      if ($create) {
        $customer= \QuickBooksOnline\API\Facades\Customer::create([
          'DisplayName' => $name
        ]);
        $res= $this->qb->Add($customer);
        if (!$res) {
          throw new \Exception("Not able to create customer '$name'");
        }
        $res= [ $res ]; // ugly, just to fix our reference below
      } else {
        throw new \Exception("Not able to find customer '$name'");
      }
    }

    return ($cache[$name] = [
      'value' => $res[0]->Id,
    ]);
  }

  protected function generateLine($memo, $account, $dbcr) {
    if ($dbcr == 0)
      return;

    return [
      "Description" => $memo,
      "Amount" => $dbcr < 0 ? substr($dbcr, 1) : $dbcr,
      "DetailType" => "JournalEntryLineDetail",
      "JournalEntryLineDetail" => [
        "PostingType" => $dbcr < 0 ? "Credit" : "Debit",
        "AccountRef" => $this->getAccountByName($account),
        "Entity" => [
          "EntityRef" => $this->getCustomerByName("Retail Customer"),
          "Type" => 'Customer'
        ],
      ]
    ];
  }
}
