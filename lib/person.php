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
              deleted
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

function person_load_activity($db, $id) {
  $id= (int)$id;

  $q= "SELECT meta, Number\$txn, Created\$date,
              Ordered, Allocated,
              CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                   AS DECIMAL(9,2))
              Total\$dollar,
              Paid\$dollar
        FROM (SELECT
              txn.type AS meta,
              CONCAT(txn.id, '|', type, '|', txn.number) AS Number\$txn,
              txn.created AS Created\$date,
              CONCAT(txn.person, '|', IFNULL(person.company,''),
                     '|', IFNULL(person.name,''))
                AS Person\$person,
              SUM(ordered) * IF(txn.type = 'customer', -1, 1) AS Ordered,
              SUM(allocated) * IF(txn.type = 'customer', -1, 1) AS Allocated,
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
              CAST((SELECT SUM(amount) FROM payment WHERE txn.id = payment.txn)
                   AS DECIMAL(9,2)) AS Paid\$dollar
         FROM txn
         LEFT JOIN txn_line ON (txn.id = txn_line.txn)
         LEFT JOIN person ON (txn.person = person.id)
        WHERE person = $id
        GROUP BY txn.id
        ORDER BY created DESC
        LIMIT 50) t";

  $r= $db->query($q);

  if ($r->num_rows) {
    while ($row= $r->fetch_row()) {
      $activity[]= $row;
    }
  }

  return $activity;
}
