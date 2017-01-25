<?

function person_load($db, $id) {
  $q= "SELECT id, 
              name,
              role,
              company,
              address,
              email,
              phone,
              tax_id,
              payment_account_id,
              active,
              deleted,
              (SELECT SUM(points) FROM loyalty WHERE person_id = person.id AND DATE(processed) < DATE(NOW())) points_available,
              (SELECT SUM(points) FROM loyalty WHERE person_id = person.id AND DATE(processed) = DATE(NOW())) points_pending
         FROM person
        WHERE id = " . (int)$id;

  $r= $db->query($q)
    or die_query($db, $q);

  $person= $r->fetch_assoc();

  if (!$person) {
    while ($field= $r->fetch_field()) {
      $person[$field->name]= null;
    }
  }

  return $person;
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
