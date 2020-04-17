<?

define('PERSON_FIND_EMPTY', 1);
define('PERSON_FIND_ALL', 2);

function person_find($db, $q, $options= null, $limit= null) {
  $criteria= [];

  $terms= preg_split('/\s+/', trim($q));
  foreach ($terms as $term) {
    if (preg_match('/id:(\d*)/', $term, $m)) {
      $id= (int)$m[1];
      $criteria[]= "(person.id = $id)";
      $options |= PERSON_FIND_ALL;
    } else if (preg_match('/role:(.+)/', $term, $m)) {
      $role= $db->escape($m[1]);
      $criteria[]= "(person.role = '$role')";
    } elseif (preg_match('/^active:(.+)/i', $term, $dbt)) {
      $criteria[]= $dbt[1] ? "(person.active)" : "(NOT person.active)";
      $options |= PERSON_FIND_ALL;
    } else {
      $term= $db->escape($term);
      $criteria[]= "(person.name LIKE '%$term%'
                 OR person.company LIKE '%$term%'
                 OR person.email LIKE '%$term%'
                 OR person.loyalty_number LIKE '%$term%'
                 OR person.phone LIKE '%$term%'
                 OR person.notes LIKE '%$term%')";
    }
  }

  if (!($options & PERSON_FIND_ALL)) {
    $criteria[]= "person.active AND NOT person.deleted";
  }

  $sql_criteria= join(' AND ', $criteria);

  $sql_limit= (int)$limit ? "LIMIT " . (int)$limit : '';

  $q= "SELECT id, name, role, company,
              address,
              email, email_ok,
              phone, loyalty_number, sms_ok,
              tax_id, payment_account_id,
              vendor_rebate,
              birthday, notes,
              url, instagram,
              active, deleted,
              suppress_loyalty,
              IF(suppress_loyalty, 0, 
                 (SELECT SUM(points)
                    FROM loyalty
                   WHERE person_id = person.id
                     AND (points < 0 OR
                          DATE(processed) < DATE(NOW())))) points_available,
              if (suppress_loyalty, 0,
                  (SELECT SUM(points)
                     FROM loyalty
                    WHERE person_id = person.id
                      AND (points > 0 AND
                           DATE(processed) = DATE(NOW())))) points_pending
         FROM person
        WHERE $sql_criteria
        ORDER BY company, name, loyalty_number
        $sql_limit";

  $r= $db->query($q)
    or die_query($db, $q);

  $people= array();

  while ($person= $r->fetch_assoc()) {
    $person['pretty_phone']= format_phone($person['loyalty_number']);
    $person['points_available']= (int)$person['points_available'];
    $person['points_pending']= (int)$person['points_pending'];
    $person['suppress_loyalty']= (int)$person['suppress_loyalty'];
    $person['rewards']= available_loyalty_items($db,
                                                $person['points_available']);
    $person['active']= (int)$person['active'];
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

function person_load_activity($db, $id, $page= 0, $page_size= 50) {
  $id= (int)$id;

  $offset= $page * $page_size;

  $limit= "";
  if ($page_size) {
    $limit= "LIMIT $offset, $page_size";
  }

  $q= "SELECT SQL_CALC_FOUND_ROWS
              txn.type,
              CONCAT(txn.id, '|', type, '|', txn.number) AS number,
              txn.created,
              (SELECT SUM(ordered) * IF(txn.type = 'customer', -1, 1)
                 FROM txn_line WHERE txn_line.txn_id = txn.id) ordered,
              (SELECT SUM(allocated) * IF(txn.type = 'customer', -1, 1)
                 FROM txn_line WHERE txn_line.txn_id = txn.id) allocated,
              (SELECT CAST(ROUND_TO_EVEN(
                            SUM(IF(txn_line.taxfree, 1, 0) *
                                IF(type = 'customer', -1, 1) * allocated *
                                sale_price(retail_price, discount_type,
                                           discount)),
                            2) AS DECIMAL(9,2))
                 FROM txn_line WHERE txn_line.txn_id = txn.id) +
              CAST((SELECT CAST(ROUND_TO_EVEN(
                                  SUM(IF(txn_line.taxfree, 0, 1) *
                                      IF(type = 'customer', -1, 1) * allocated *
                                      sale_price(retail_price, discount_type,
                                                 discount)),
                                  2) AS DECIMAL(9,2))
                      FROM txn_line WHERE txn_line.txn_id = txn.id) *
                (1 + IFNULL(tax_rate,0)/100) AS DECIMAL(9,2))
              total,
              CAST((SELECT SUM(amount) FROM payment WHERE txn.id = payment.txn_id)
                   AS DECIMAL(9,2)) AS paid
         FROM txn
        WHERE person_id = $id
        GROUP BY txn.id
        ORDER BY created DESC
        $limit";

  $r= $db->query($q);

  if (!$r) return;

  $activity= array();

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

function available_loyalty_items($db, $points) {
  $q= "SELECT item_id AS id, cost, code, name, retail_price
        FROM loyalty_reward
        JOIN item ON item.id = item_id
       WHERE cost <= $points
       ORDER BY cost DESC";

  $r= $db->query($q)
    or die_query($db, $q);

  $rewards= array();
  while ($row= $r->fetch_assoc()) {
    $row['cost']= (int)$row['cost'];
    $row['retail_price']= (float)$row['retail_price'];
    $rewards[]= $row;
  }

  return $rewards;
}
