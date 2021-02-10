<?php
namespace Scat\Controller;

use \Slim\Http\ServerRequest as Request;
use \Slim\Http\Response as Response;
use \Slim\Views\Twig as View;
use \Respect\Validation\Validator as v;

class People {
  public function home(Request $request, Response $response, View $view,
                        \Scat\Service\Data $data)
  {
    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $view->render($response, 'dialog/person-edit.html', [ ]);
    }

    $limit= $request->getParam('limit') ?: 20;
    $people= $data->factory('Person')
                  ->select('*')
                  ->select_expr('(SELECT MAX(IFNULL(txn.paid, txn.created))
                                    FROM txn WHERE txn.person_id = person.id)',
                                'latest')
                  ->where('active', 1)
                  ->order_by_desc('latest')
                  ->limit($limit)
                  ->find_many();

    $accept= $request->getHeaderLine('Accept');
    if ($select2 || strpos($accept, 'application/json') !== false) {
      return $response->withJson($people);
    }
    return $view->render($response, 'person/index.html', [
      'people' => $people
    ]);
  }

  public function search(Request $request, Response $response, View $view,
                          \Scat\Service\Data $data) {
    $q= trim($request->getParam('q'));
    $loyalty= trim($request->getParam('loyalty'));

    if ($q) {
      $people= \Scat\Model\Person::find($q);
    } elseif ($loyalty) {
      $loyalty_number= preg_replace('/[^\d]/', '', $loyalty);
      $person= $data->factory('Person')
                    ->where_any_is([
                      [ 'loyalty_number' => $loyalty_number ?: 'no' ],
                      [ 'email' => $loyalty ]
                    ])
                    ->where('active', 1)
                    ->find_one();
      $people= $person ? [ $person ] : [];
    } else {
      $people= [];
    }

    $select2= $request->getParam('_type') == 'query';
    $accept= $request->getHeaderLine('Accept');
    if ($select2 || strpos($accept, 'application/json') !== false) {
      return $response->withJson($people);
    }

    return $view->render($response, 'person/index.html', [
      'people' => $people,
      'q' => $q
    ]);
  }

  public function person(Request $request, Response $response, $id, View $view,
                          \Scat\Service\Data $data)
  {
    $person= $data->factory('Person')->find_one($id);

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/json') !== false) {
      return $response->withJson($person);
    }

    $page= (int)$request->getParam('page');
    $limit= 25;

    return $view->render($response, 'person/person.html', [
      'person' => $person,
      'page' => $page,
      'limit' => $limit,
    ]);
  }

  public function items(Request $request, Response $response, $id, View $view,
                        \Scat\Service\Data $data) {
    $person= $data->factory('Person')->find_one($id);
    $page= (int)$request->getParam('page');

    if ($person->role != 'vendor') {
      throw new \Exception("That person is not a vendor.");
    }

    $limit= $request->getParam('limit') ?: 25;

    $q= $request->getParam('q');

    $items= \Scat\Model\VendorItem::search($person->id, $q);
    $items= $items->select_expr('COUNT(*) OVER()', 'total')
                  ->limit($limit)->offset($page * $limit);
    $items= $items->find_many();

    return $view->render($response, 'person/items.html', [
     'person' => $person,
     'items' => $items,
     'q' => $q,
     'page' => $page,
     'limit' => $limit,
     'page_size' => $page_size,
    ]);
  }

  function uploadItems(Request $request, Response $response, $id,
                        \Scat\Service\Data $data) {
    $person= $data->factory('Person')->find_one($id);
    if (!$person)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $details= [];
    foreach ($request->getUploadedFiles() as $file) {
      $details[]= $person->loadVendorData($file);
    }

    return $response->withJson($details[0]);
  }

  public function createPerson(Request $request, Response $response,
                                \Scat\Service\Data $data) {
    $person= $data->factory('Person')->create();
    return $this->processPerson($request, $response, $data, $person);
  }

  public function updatePerson(Request $request, Response $response, $id,
                                \Scat\Service\Data $data)
  {
    $person= $data->factory('Person')->find_one($id);
    if (!$person)
      throw new \Slim\Exception\HttpNotFoundException($request);

    return $this->processPerson($request, $response, $data, $person);
  }

  public function mergePerson(Request $request, Response $response,
                              \Scat\Service\Data $data, $id)
  {
    $person= $data->factory('Person')->find_one($id);
    if (!$person)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $from= $data->factory('Person')->find_one($request->getParam('from'));
    if (!$from)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if ($person->id == $from->id)
      throw new \Exception("Can't merge a person into themself");

    $data->beginTransaction();

    /* Move over devices */
    $q= "UPDATE IGNORE device
            SET person_id = {$person->id}
          WHERE person_id = {$from->id}";
    $data->execute($q);

    /* Leftovers? Nuke them. */
    $q= "DELETE FROM device
          WHERE person_id = {$from->id}";
    $data->execute($q);

    /* Move over loyalty */
    $q= "UPDATE IGNORE loyalty
            SET person_id = {$person->id}
          WHERE person_id = {$from->id}";
    $data->execute($q);

    /* Change references to old person to new one */
    $q= "UPDATE note
            SET person_id = {$person->id}
          WHERE person_id = {$from->id}";
    $data->execute($q);

    $q= "UPDATE note
            SET attach_id = {$person->id}
          WHERE attach_id = {$from->id}
            AND kind = 'person'";
    $data->execute($q);

    $q= "UPDATE timeclock
            SET person_id = {$person->id}
          WHERE person_id = {$from->id}";
    $data->execute($q);

    $q= "UPDATE txn
            SET person_id = {$person->id}
          WHERE person_id = {$from->id}";
    $data->execute($q);

    $q= "UPDATE vendor_item
            SET vendor_id = {$person->id}
          WHERE vendor_id = {$from->id}";
    $data->execute($q);

    $from->delete();

    $data->commit();

    return $response->withJson($person);
  }

  public function processPerson(Request $request, Response $response,
                                \Scat\Service\Data $data, $person) {
    $dirty= false;
    foreach ($person->getFields() as $field) {
      if ($field == 'id') continue; // don't allow changing id
      $value= $request->getParam($field);
      if ($value !== null) {
        $person->setProperty($field, $value);
        $dirty= true;
      }
    }

    $new= $person->is_new();

    if ($dirty) {
      try {
        $person->save();
      } catch (\PDOException $e) {
        if ($e->getCode() == '23000') {
          throw new \Scat\Exception\HttpConflictException($request);
        } else {
          throw $e;
        }
      }
    } else {
      return $response->withStatus(304);
    }

    if ($new) {
      $response= $response->withStatus(201)
                          ->withHeader('Location', '/person/' . $person->id);
    }

    return $response->withJson($person);
  }

  public function loyalty(Request $request, Response $response, $id, View $view,
                          \Scat\Service\Data $data) {
    $person= $data->factory('Person')->find_one($id);

    $activity= $person->loyalty()->order_by_desc('id')->find_many();

    return $response->withJson($activity);
  }

  public function remarketingList(Request $request, Response $response,
                                  \Scat\Service\Data $data)
  {
    $people= $data->factory('Person')
      ->where('role', 'customer')
      ->where('active', 1)
      ->find_many();

    $fields= [ 'Email', 'Phone' ];

    //$output= fopen("php://temp/maxmemory:" . (5*1024*1024), 'r+');
    $output= fopen("php://memory", 'r+');
    fputcsv($output, $fields);

    $phoneUtil= \libphonenumber\PhoneNumberUtil::getInstance();

    foreach ($people as $person) {
      $email= strtolower(trim($person->email));
      try {
        $phone= $phoneUtil->parse($person->phone, 'US');
        $phone= $phoneUtil->format($phone,
                                    \libphonenumber\PhoneNumberFormat::E164);
      } catch (\Exception $e) {
        $phone= '';
      }
      if (!$phone && !$email) continue;
      fputcsv($output, [
        $email ? hash('sha256', $email) : '',
        $phone ? hash('sha256', $phone) : ''
      ]);
    }

    $response= $response->withBody(\GuzzleHttp\Psr7\stream_for($output));

    return $response->withHeader("Content-type", "text/csv");
  }

  public function getTaxExemption(Request $request, Response $response, $id,
                                  \Scat\Service\Data $data,
                                  \Scat\Service\Tax $tax,
                                  View $view)
  {
    $person= $data->factory('Person')->find_one($id);
    if (!$person)
      throw new \Slim\Exception\HttpNotFoundException($request);

    if ($person->exemption_certificate_id) {
      $exemption= $tax->getExemptCertificates([ 'customerID' => $person->id ]);
    }

    $accept= $request->getHeaderLine('Accept');
    if (strpos($accept, 'application/vnd.scat.dialog+html') !== false) {
      return $view->render($response, 'dialog/tax-exemption.html', [
        'person' => $person,
        'exemption' => $exemption->ExemptCertificates[0],
      ]);
    }

    return $response->withJson($person->tax_exemption_certificate);
  }

  public function setTaxExemption(Request $request, Response $response, $id,
                                  \Scat\Service\Data $data,
                                  \Scat\Service\Tax $tax)
  {
    $person= $data->factory('Person')->find_one($id);
    if (!$person)
      throw new \Slim\Exception\HttpNotFoundException($request);

    $data= [
      'customerID' => $person->id,
      'exemptCert' => [
        'Detail' => [
          'ExemptStates' => [
            [
              'IdentificationNumber' => 1,
              'ReasonForExemption' =>
                $request->getParam('exemption_reason'),
              'StateAbbr' => $request->getParam('state'),
            ],
          ],
          'PurchaserBusinessType' =>
            $request->getParam('business_type'),
          'PurchaserExemptionReason' =>
            $request->getParam('exemption_reason'),
          'PurchaserTaxID' => [
            'IDNumber' => $request->getParam('tax_id'),
            'StateOfIssue' => $request->getParam('state'),
            'TaxType' => 'StateIssued',
          ],
          'PurchaserFirstName' =>
            $request->getParam('first_name'),
          'PurchaserLastName' =>
            $request->getParam('last_name'),
          'PurchaserAddress1' =>
            $request->getParam('address1'),
          'PurchaserAddress2' =>
            $request->getParam('address2'),
          'PurchaserCity' =>
            $request->getParam('city'),
          'PurchaserState' =>
            $request->getParam('state'),
          'PurchaserZip' =>
            $request->getParam('zip'),
        ],
      ],
    ];

    $res= $tax->addExemptCertificate($data);

    if ($res->ResponseType < 2) {
      throw new \Exception($res->Messages[0]->Message);
    }

    // Had an old one? Go ahead and delete it.
    if ($person->exemption_certificate_id) {
      $tax->deleteExemptCertificate([
        'certificateId' => $person->exemption_certificate_id
      ]);
    }

    $person->exemption_certificate_id= $res->CertificateID;
    $person->save();

    return $response->withJson([ 'message' => 'Success!' ]);
  }
}
