<?
include '../scat.php';
include '../lib/person.php';
include '../lib/eps-express.php';

$person_id= (int)$_REQUEST['person'];
$person= $person_id ? person_load($db, $person_id) : false;

if (!$person_id || !$person || !$person['payment_account_id'])
  die_jsonp("No person specified or no card stored for person.");

$eps= new EPS_Express();

$response= $eps->PaymentAccountDelete($person['payment_account_id']);

if ($response->ExpressResponseCode != 0) {
  die_jsonp((string)$response->ExpressResponseMessage);
}

// remove payment account info from person
$q= "UPDATE person
        SET payment_account_id = NULL
      WHERE id = $person_id";
$r= $db->query($q)
  or die_query($db, $q);

echo jsonp(array('person' => person_load($db, $person_id),
                 'response' => $response));
