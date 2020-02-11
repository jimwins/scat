<?php
require 'scat.php';

$config= [
  'auth_mode' => 'oauth2',
  'ClientID' => QB_CLIENT_ID,
  'ClientSecret' => QB_CLIENT_SECRET,
  'RedirectURI' => QB_REDIRECT_URI,
  'baseUrl' => QB_BASE_URL,
  'scope' => QB_SCOPE,
];

head("QuickBooks Connection");

if ($_REQUEST['disconnect']) {
  \Scat\Config::forgetValue('qb.accessToken');
  \Scat\Config::forgetValue('qb.refreshToken');
  \Scat\Config::forgetValue('qb.realmId');
}

// Logging in
if ($_REQUEST['code']) {
  $qb= \QuickBooksOnline\API\DataService\DataService::Configure($config);
  $helper= $qb->getOAuth2LoginHelper();

  $token= $helper->exchangeAuthorizationCodeForToken(
    $_REQUEST['code'], $_REQUEST['realmId']
  );
  $qb->updateOAuth2Token($token);

  \Scat\Config::setValue('qb.accessToken', $token->getAccessToken());
  \Scat\Config::setValue('qb.refreshToken', $token->getRefreshToken());
  \Scat\Config::setValue('qb.realmId', $_REQUEST['realmId']);

  echo '<p>Obtained and saved token.</p>';
} else {

  $accessToken= \Scat\Config::getValue('qb.accessToken');
  $refreshToken= \Scat\Config::getValue('qb.refreshToken');
  $realmId= \Scat\Config::getValue('qb.realmId');

  if ($accessToken && $refreshToken && $realmId) {
    $config['accessTokenKey']= $accessToken;
    $config['refreshTokenKey']= $refreshToken;
    $config['QBORealmID']= $realmId;
  }

  $qb= \QuickBooksOnline\API\DataService\DataService::Configure($config);
  $helper= $qb->getOAuth2LoginHelper();

  if ($refreshToken) {
    $token= $helper->refreshToken();
    \Scat\Config::setValue('qb.accessToken', $token->getAccessToken());
    \Scat\Config::setValue('qb.refreshToken', $token->getRefreshToken());
    $qb->updateOAuth2Token($token);
  }
}

if (!$token) {
  $authUrl= $helper->getAuthorizationCodeURL();
?>
  <a class="btn btn-primary" href="<?=$authUrl?>">
    Connect to QuickBooks
  </a>
<?
  goto end;
}
?>
  <div class="pull-right">
    <a class="btn btn-danger" href="qb.php?disconnect=1">
      Disconnect
    </a>
  </div>
<?
$info= $qb->getCompanyInfo();
?>
<h1 class="page-title"><?=$info->CompanyName?></h1>
<div class="row">
  <div class="col-sm-4">
    <a href="qb.php?verify=1" class="btn btn-default">
      Verify Accounts
    </a>
    <a href="qb.php?lookup=1" class="btn btn-default">
      Look up Accounts
    </a>
  </div>
  <div class="col-sm-4">
    <form method="POST" action="qb.php">
      <input type="hidden" name="payments" value="1">
      <input type="date" class="form-control" name="date" value="<?=$db->get_one("SELECT MIN(DATE(processed)) FROM payment WHERE amount > 0 AND qb_je_id = '' AND processed > '2020-01-01'")?>">
      <button type="submit" class="btn btn-default">
        Load Payments
      </button>
    </form>
  </div>
  <div class="col-sm-4">
    <form method="POST" action="qb.php">
      <input type="hidden" name="sales" value="1">
      <input type="date" class="form-control" name="date" value="<?=$db->get_one("SELECT MIN(DATE(paid)) FROM txn WHERE type='customer' AND qb_je_id = '' AND paid > '2020-01-01'")?>">
      <button type="submit" class="btn btn-default">
        Load Sales
      </button>
    </form>
  </div>
<br>
<?
$qb_accounts= [
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

if ($_REQUEST['lookup']) {
  echo '<pre>';
  $i= 1;
  while (1) {
      $allAccounts= $qb->FindAll('Account', $i, 500);
      $error= $qb->getLastError();
      if ($error) {
          echo "The Status code is: " . $error->getHttpStatusCode() . "\n";
          echo "The Helper message is: " . $error->getOAuthHelperError() . "\n";
          echo "The Response message is: " . $error->getResponseBody() . "\n";
          goto end;
      }

      if (!$allAccounts || (0==count($allAccounts))) {
          break;
      }

      foreach ($allAccounts as $oneAccount) {
          echo "Account[".($i++)."]: {$oneAccount->Name}\n";
          echo "\t * Id: [{$oneAccount->Id}]\n";
          echo "\t * AccountType: [{$oneAccount->AccountType}]\n";
          echo "\t * AccountSubType: [{$oneAccount->AccountSubType}]\n";
          echo "\t * Active: [{$oneAccount->Active}]\n";
          echo "\n";
      }
  }

  echo '</pre>';
}

if ($_REQUEST['verify']) {
  foreach ($qb_accounts as $i => $account) {
    try {
      getAccountByName($qb, $account[0]);
    } catch (\Exception $e) {
      echo '<a href="qb.php?createAccount=',  $i, '">',
           'Create "', ashtml($account[0]), '"',
           '</a><br>';
    }
  }
}

if (array_key_exists('createAccount', $_REQUEST)) {
  $num= $_REQUEST['createAccount'];
  $obj= \QuickBooksOnline\API\Facades\Account::create([
    'Name' => $qb_accounts[$num][0],
    'AccountType' => $qb_accounts[$num][1],
    'AccountSubType' => $qb_accounts[$num][2]
  ]);

  $res= $qb->Add($obj);
  $error= $qb->getLastError();
  if ($error) {
    echo "The Status code is: " . $error->getHttpStatusCode() . "\n";
    echo "The Helper message is: " . $error->getOAuthHelperError() . "\n";
    echo "The Response message is: " . $error->getResponseBody() . "\n";
  }
  else {
    echo "Created Id={$res->Id}<br>";
  }
}

if ($_REQUEST['payments']) {
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

  $date= (new DateTime($_REQUEST['date']))->format('Y-m-d');

  echo "<h2>Loading payments for '$date'</h2>";

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
      die("ERROR: Can't handle '$method' for '{$txn->type}' payment");
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
            "AccountRef" => getAccountByName($qb, $debit),
            "Entity" => [
              "EntityRef" => getCustomerByName($qb, "Retail Customer"),
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
            "AccountRef" => getAccountByName($qb, $credit),
            "Entity" => [
              "EntityRef" => getCustomerByName($qb, "Retail Customer"),
              "Type" => 'Customer'
            ],
          ]
        ],
      ]
    ];

    $je= \QuickBooksOnline\API\Facades\JournalEntry::create($data);

    $res= $qb->Add($je);
    $error = $qb->getLastError();
    if ($error) {
      echo "The Status code is: " . $error->getHttpStatusCode() . "<br>";
      echo "The Helper message is: " . $error->getOAuthHelperError() . "<br>";
      echo "The Response message is: " . $error->getResponseBody() . "<br>";
    }
    else {
      echo "Created Id={$res->Id}<br>";
      $pay->qb_je_id= $res->Id;
      $pay->save();
    }
  }
}

if ($_REQUEST['sales']) {
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

  $date= (new DateTime($_REQUEST['date']))->format('Y-m-d');

  echo "<h2>Loading sales for '$date'</h2>";

  $txns= \Model::factory('Txn')
          ->where_raw("qb_je_id = '' AND
                       ((type = 'correction' AND
                         created BETWEEN ? AND ? + INTERVAL 1 DAY) OR
                        (type = 'customer' AND
                         paid BETWEEN ? AND ? + INTERVAL 1 DAY))",
                        [ $date, $date, $date, $date ])
          ->find_many();

  foreach ($txns as $txn) {
    switch ($txn->type) {
    case 'correction':
      if ($txn->total() == 0)
        continue 2;

      $memo= "{$txn->id}: Correction {$txn->formatted_number()}";

      $data= [
        "DocNumber" => 'S' . $txn->id,
        "TxnDate" => (new \DateTime($txn->created))->format('Y-m-d'),
        'Line' => [
          generateLine($qb, $memo, 'Shrinkage',    bcmul($txn->total(),-1)),
          generateLine($qb, $memo, 'Art Supplies', $txn->total()),
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
          generateLine($qb, $memo, 'Accounts Receivable', $txn->total()),
        ]
      ];

      if ($txn->tax() != 0) {
        $data['Line'][]= generateLine($qb, $memo, 'Sales Tax Payable',
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

#echo '<pre>' . json_encode($sales, JSON_PRETTY_PRINT), '</pre>';
#echo "costs: $costs, total: $total, subtotal: {$txn->subtotal()}<br>";
      // $txn->subtotal has polarity opposite what we've done for total
      if ($total != bcmul($txn->subtotal(), -1)) {
        $sales['supplies']= bcsub($sales['supplies'],
                                  bcadd($txn->subtotal(), $total));
      }

      foreach ($sales as $category => $amount) {
        // sale
        $data['Line'][]= generateLine($qb, $memo, $account[$category], $amount);
      }

      if ($costs != "0.00") {
        $data['Line'][]= generateLine($qb, $memo, $account['inventory'],   $costs);
        $data['Line'][]= generateLine($qb, $memo, $account['costofgoods'], bcmul($costs, -1));
      }

      break;

    default:
      die("ERROR: Unable to handle transaction type '$sale[type]'");
    }

    $data['Line']= array_values(array_filter($data['Line']));

    if (count($data['Line']) == 0) {
      echo "Transaction {$txn->id} was a wash, skipping";
      $txn->qb_je_id= 'skipped';
      $txn->save();
      continue;
    }

#echo '<pre>' . json_encode($data, JSON_PRETTY_PRINT), '</pre>';
#goto end;

    $je= \QuickBooksOnline\API\Facades\JournalEntry::create($data);

    $res= $qb->Add($je);
    $error = $qb->getLastError();
    if ($error) {
      echo "The Status code is: " . $error->getHttpStatusCode() . "<br>";
      echo "The Helper message is: " . $error->getOAuthHelperError() . "<br>";
      echo "The Response message is: " . $error->getResponseBody() . "<br>";
      echo "Got this for {$txn->id}";
      echo '<pre>', json_encode($data, JSON_PRETTY_PRINT), '</pre>';
    }
    else {
      $txn->qb_je_id= $res->Id;
      $txn->save();
      echo "Created Id={$res->Id}<br>";
    }
  }
}

// memoization is fun
function getAccountByName($qb, $name) {
  static $cache= [];

  $name= addslashes($name);

  if ($cache[$name]) {
    return $cache[$name];
  }

  $res= $qb->Query("SELECT * FROM Account WHERE name = '$name'");
  if (!$res) {
    throw new \Exception("Not able to find account '$name'");
  }

  return ($cache[$name] = [
    'value' => $res[0]->Id,
    'name' => $res[0]->Name,
  ]);
}

function getCustomerByName($qb, $name, $create= true) {
  static $cache= [];

  $name= addslashes($name);

  if ($cache[$name]) {
    return $cache[$name];
  }

  $res= $qb->Query("SELECT * FROM Customer WHERE DisplayName = '$name'");
  if (!$res) {
    if ($create) {
      $customer= \QuickBooksOnline\API\Facades\Customer::create([
        'DisplayName' => $name
      ]);
      $res= $qb->Add($customer);
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

function generateLine($qb, $memo, $account, $dbcr) {
  if ($dbcr == 0)
    return;

  return [
    "Description" => $memo,
    "Amount" => $dbcr < 0 ? substr($dbcr, 1) : $dbcr,
    "DetailType" => "JournalEntryLineDetail",
    "JournalEntryLineDetail" => [
      "PostingType" => $dbcr < 0 ? "Credit" : "Debit",
      "AccountRef" => getAccountByName($qb, $account),
      "Entity" => [
        "EntityRef" => getCustomerByName($qb, "Retail Customer"),
        "Type" => 'Customer'
      ],
    ]
  ];
}

end:
foot();
