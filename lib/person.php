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
    return array('id' => 0, 'name' => '');
  }

  return $person;
}
