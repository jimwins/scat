<?

function person_load($db, $id) {
  $q= "SELECT id, 
              name,
              company,
              address,
              email,
              phone,
              tax_id,
              active,
              deleted
         FROM person
        WHERE id = $id";

  $r= $db->query($q)
    or die_query($db, $q);

  $person = $r->fetch_assoc();

  return $person;
}

