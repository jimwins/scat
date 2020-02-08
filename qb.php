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
    <a href="qb.php?lookup=1" class="btn btn-default">
      Look up Accounts
    </a>
  </div>
  <div class="col-sm-4">
    <form method="POST" action="qb.php">
      <input type="hidden" name="payments" value="1">
      <input type="date" class="form-control" name="date" value="2020-01-03">
      <button type="submit" class="btn btn-default">
        Load Payments
      </button>
    </form>
  </div>
  <div class="col-sm-4">
    <form method="POST" action="qb.php">
      <input type="hidden" name="sales" value="1">
      <input type="date" class="form-control" name="date" value="2020-01-03">
      <button type="submit" class="btn btn-default">
        Load Sales
      </button>
    </form>
  </div>
<br>
<?
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
      echo "Created Id={$res->Id}";
      $pay->qb_je_id= $res->Id;
      $pay->save();
    }
  }
}

if ($_REQUEST['sales']) {
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

  $date= $_REQUEST['date'];
  $range= "'$date' AND '$date' + INTERVAL 1 DAY";
  $q= "SELECT id, type, created,
              DATE_FORMAT(IF(type = 'customer', paid, created), '%Y-%m-%d') date,
              CONCAT(YEAR(IF(type = 'customer', paid, created)), '-', number) num,
              taxed, untaxed,
              CAST(tax_rate AS DECIMAL(9,2)) tax_rate,
              taxed + untaxed subtotal,
              IF(uuid, tax,
                 CAST(ROUND_TO_EVEN(taxed * (tax_rate / 100), 2)
                      AS DECIMAL(9,2))) tax,
              IF(uuid, untaxed + taxed + tax,
                 CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                      AS DECIMAL(9,2))) total
        FROM (SELECT
              txn.id, txn.uuid, txn.type, txn.number,
              txn.created, txn.filled, txn.paid,
              SUM(ordered) * IF(txn.type = 'customer', -1, 1) AS ordered,
              SUM(allocated) * IF(txn.type = 'customer', -1, 1) AS allocated,
              CAST(ROUND_TO_EVEN(
                SUM(IF(txn_line.taxfree, 1, 0) *
                  IF(type = 'customer', -1, 1) * allocated *
                  sale_price(retail_price, discount_type, discount)),
                2) AS DECIMAL(9,2))
              untaxed,
              CAST(ROUND_TO_EVEN(
                SUM(IF(txn_line.taxfree, 0, 1) *
                  IF(type = 'customer', -1, 1) * allocated *
                  sale_price(retail_price, discount_type, discount)),
                2) AS DECIMAL(9,2))
              taxed,
              tax_rate,
              SUM(tax) AS tax
         FROM txn
         LEFT JOIN txn_line ON (txn.id = txn_line.txn)
        WHERE (type = 'correction' AND created BETWEEN $range)
           OR (type = 'customer'   AND paid    BETWEEN $range)
        GROUP BY txn.id
        ORDER BY id) t";

  $r= $db->query($q)
    or die_query($db, $q);

  $account= array(
                  // assets
                  'receivable' => 'Accounts Receivable',
                  'inventory'  => 'Art Supplies',
                  // liabilities
                  'gift'       => 'Unclaimed Gift Certificates',
                  'salestax'   => 'Sales Tax Payable',
                  // sales
                  'art'        => 'Art Supplies',
                  'supplies'   => 'Art Supplies',
                  'class'      => 'Class Fees',
                  'freight'    => 'Delivery/Shipping & Handling',
                  // cost of sales
                  'costofgoods'=> 'Cost of Goods Sold',
                  'loyalty'    => 'Discounts Given',
                  // shrink
                  'shrink'     => 'Shrinkage',
                 );

  while ($sale= $r->fetch_assoc()) {

    switch ($sale['type']) {
    case 'correction':
      if ($sale['total'] == 0)
        continue 2;

      $memo= "$sale[id]: Correction $sale[num]";


      // create blank entry
      $data= [
        "DocNumber" => 'S' . $sale['id'],
        "TxnDate" => $sale['date'],
        'Line' => [
          generateLine($qb, $memo, 'Shrinkage',    bcmul($sale['total'],-1)),
          generateLine($qb, $memo, 'Art Supplies', $sale['total']),
        ]
      ];

      break;

    case 'customer':
      $memo= "$sale[id]: Invoice $sale[num]";

      $data= [
        "DocNumber" => 'S' . $sale['id'],
        "TxnDate" => $sale['date'],
        'Line' => [
          // receivable
          generateLine($qb, $memo, 'Accounts Receivable', $sale['total']),
        ]
      ];

      if ($sale['tax_rate']) {
        $data['Line'][]= generateLine($qb, $memo, 'Sales Tax Payable',
                               bcmul($sale['tax'], -1));
      }

      $q= "SELECT code,
                  CAST(IFNULL(ROUND_TO_EVEN(
                      allocated *
                      (SELECT ROUND_TO_EVEN(AVG(tl.retail_price), 2)
                         FROM txn JOIN txn_line tl ON txn.id = tl.txn
                        WHERE type = 'vendor'
                          AND item = txn_line.item
                          AND filled < '$sale[created]'
                       ),
                      2), 0.00) AS DECIMAL(9,2)) AS cost,
                  CAST(ROUND_TO_EVEN(
                    allocated *
                    CASE txn_line.discount_type
                      WHEN 'percentage' THEN txn_line.retail_price *
                                           ((100 - txn_line.discount) / 100)
                      WHEN 'relative' THEN (txn_line.retail_price -
                                            txn_line.discount)
                      WHEN 'fixed' THEN (txn_line.discount)
                      ELSE txn_line.retail_price
                    END, 2) AS DECIMAL(9,2)) AS price
             FROM txn_line
             JOIN item ON txn_line.item = item.id
            WHERE txn = $sale[id]";

      $in= $db->query($q)
        or die_query($db, $q);

      $sales= array();
      $costs= $total= "0.00";

      while ($line= $in->fetch_assoc()) {
        $category= 'supplies';
        if (preg_match('/^ZZ-frame/i', $line['code'])) {
          $category= 'framing';
        } elseif (preg_match('/^ZZ-(print|scan)/i', $line['code'])) {
          $category= 'printing';
        } elseif (preg_match('/^ZZ-art/i', $line['code'])) {
          $category= 'art';
        } elseif (preg_match('/^ZZ-online/i', $line['code'])) {
          $category= 'online';
        } elseif (preg_match('/^ZZ-class/i', $line['code'])) {
          $category= 'class';
        } elseif (preg_match('/^ZZ-gift/i', $line['code'])) {
          $category= 'gift';
        } elseif (preg_match('/^ZZ-loyalty/i', $line['code'])) {
          $category= 'loyalty';
        } elseif (preg_match('/^ZZ-shipping/i', $line['code'])) {
          $category= 'freight';
        }

        $sales[$category]= bcadd($sales[$category], $line['price']);
        $total= bcadd($total, $line['price']);
        $costs= bcadd($costs, $line['cost']);
      }

      // $sale[subtotal] is has polarity opposite what we've done for total
      if ($total != bcmul($sale['subtotal'], -1)) {
        $sales['supplies']= bcsub($sales['supplies'],
                                  bcadd($sale['subtotal'], $total));
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

    $je= \QuickBooksOnline\API\Facades\JournalEntry::create($data);

    $res= $qb->Add($je);
    $error = $qb->getLastError();
    if ($error) {
      echo "The Status code is: " . $error->getHttpStatusCode() . "<br>";
      echo "The Helper message is: " . $error->getOAuthHelperError() . "<br>";
      echo "The Response message is: " . $error->getResponseBody() . "<br>";
    }
    else {
      echo "Created Id={$res->Id}";
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
  ]);
}

function getCustomerByName($qb, $name) {
  static $cache= [];

  $name= addslashes($name);

  if ($cache[$name]) {
    return $cache[$name];
  }

  $res= $qb->Query("SELECT * FROM Customer WHERE DisplayName = '$name'");
  if (!$res) {
    throw new \Exception("Not able to find account '$name'");
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
