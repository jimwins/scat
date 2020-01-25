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

if ($_GET['disconnect']) {
  \Scat\Config::forgetValue('qb.accessToken');
  \Scat\Config::forgetValue('qb.refreshToken');
  \Scat\Config::forgetValue('qb.realmId');
}

// Logging in
if ($_GET['code']) {
  $qb= \QuickBooksOnline\API\DataService\DataService::Configure($config);
  $helper= $qb->getOAuth2LoginHelper();

  $token= $helper->exchangeAuthorizationCodeForToken(
    $_GET['code'], $_GET['realmId']
  );
  $qb->updateOAuth2Token($token);

  \Scat\Config::setValue('qb.accessToken', $token->getAccessToken());
  \Scat\Config::setValue('qb.refreshToken', $token->getRefreshToken());
  \Scat\Config::setValue('qb.realmId', $_GET['realmId']);

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
    echo '<p>Updated token.</p>';
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

$companyInfo = $qb->getCompanyInfo();
$address = "QBO API call Successful!! Response Company name: " . $companyInfo->CompanyName . " Company Address: " . $companyInfo->CompanyAddr->Line1 . " " . $companyInfo->CompanyAddr->City . " " . $companyInfo->CompanyAddr->PostalCode;
print_r($address);

end:
foot();
