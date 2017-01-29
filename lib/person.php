<?

define('PERSON_FIND_EMPTY', 1);

function person_find($db, $q, $options= null) {
  $criteria= array("active AND NOT deleted");

  $terms= preg_split('/\s+/', trim($q));
  foreach ($terms as $term) {
    if (preg_match('/id:(\d+)/', $term, $m)) {
      $criteria[]= "(person.id = $m[1])";
    } else if (preg_match('/role:(.+)/', $term, $m)) {
      $role= $db->escape($m[1]);
      $criteria[]= "(person.role = '$role')";
    } else {
      $term= $db->escape($term);
      $criteria[]= "(person.name LIKE '%$term%'
                 OR person.company LIKE '%$term%'
                 OR person.email LIKE '%$term%'
                 OR person.loyalty_number LIKE '%$term%'
                 OR person.phone LIKE '%$term%')";
    }
  }

  $sql_criteria= join(' AND ', $criteria);

  $q= "SELECT id, name, role, company,
              address,
              email, email_ok,
              phone, loyalty_number, sms_ok,
              tax_id, payment_account_id,
              birthday, notes,
              active, deleted,
              (SELECT SUM(points)
                 FROM loyalty
                WHERE person_id = person.id
                  AND (points < 0 OR
                       DATE(processed) < DATE(NOW()))) points_available,
              (SELECT SUM(points)
                 FROM loyalty
                WHERE person_id = person.id
                  AND (points > 0 AND
                       DATE(processed) = DATE(NOW()))) points_pending
         FROM person
        WHERE $sql_criteria
        ORDER BY name, company, loyalty_number";

  $r= $db->query($q)
    or die_query($db, $q);

  $people= array();

  while ($person= $r->fetch_assoc()) {
    $person['pretty_phone']= format_phone($person['loyalty_number']);
    $people[]= $person;
  }

  if (!$people && ($options & PERSON_FIND_EMPTY)) {
    while ($field= $r->fetch_field()) {
      $person[$field->name]= null;
    }
    $person['pretty_phone']= null;
    return array($person);
  }

  return $people;
}

function person_load($db, $id, $options= null) {
  $people= person_find($db, "id:$id", $options);

  return $people[0];
}

function person_load_activity($db, $id, $page= 0) {
  $id= (int)$id;

  $page_size= 50;
  $offset= $page * $page_size;

  $q= "SELECT txn.type,
              CONCAT(txn.id, '|', type, '|', txn.number) AS number,
              txn.created,
              (SELECT SUM(ordered) * IF(txn.type = 'customer', -1, 1)
                 FROM txn_line WHERE txn_line.txn = txn.id) ordered,
              (SELECT SUM(allocated) * IF(txn.type = 'customer', -1, 1)
                 FROM txn_line WHERE txn_line.txn = txn.id) allocated,
              (SELECT CAST(ROUND_TO_EVEN(
                            SUM(IF(txn_line.taxfree, 1, 0) *
                                IF(type = 'customer', -1, 1) * allocated *
                                sale_price(retail_price, discount_type,
                                           discount)),
                            2) AS DECIMAL(9,2))
                 FROM txn_line WHERE txn_line.txn = txn.id) +
              CAST((SELECT CAST(ROUND_TO_EVEN(
                                  SUM(IF(txn_line.taxfree, 0, 1) *
                                      IF(type = 'customer', -1, 1) * allocated *
                                      sale_price(retail_price, discount_type,
                                                 discount)),
                                  2) AS DECIMAL(9,2))
                      FROM txn_line WHERE txn_line.txn = txn.id) *
                (1 + IFNULL(tax_rate,0)/100) AS DECIMAL(9,2))
              total,
              CAST((SELECT SUM(amount) FROM payment WHERE txn.id = payment.txn)
                   AS DECIMAL(9,2)) AS paid
         FROM txn
        WHERE person = $id
        GROUP BY txn.id
        ORDER BY created DESC
        LIMIT $offset, 50";

  $r= $db->query($q);

  if ($r->num_rows) {
    while ($row= $r->fetch_row()) {
      $activity[]= $row;
    }
  }

  return $activity;
}

function person_load_loyalty($db, $id) {
  $id= (int)$id;

  $loyalty= array();

  $q= "SELECT processed, points, note
         FROM loyalty
        WHERE person_id = $id";

  $r= $db->query($q);

  if ($r->num_rows) {
    while ($row= $r->fetch_assoc()) {
      $loyalty[]= $row;
    }
  }

  return $loyalty;
}
