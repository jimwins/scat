<?

function txn_load($db, $id) {
  $q= "SELECT id, type,
              number, created, filled, paid,
              CONCAT(DATE_FORMAT(created, '%Y-'), number) AS formatted_number,
              person, person_name,
              IFNULL(ordered, 0) ordered, allocated,
              taxed, untaxed, tax_rate,
              taxed + untaxed subtotal,
              CAST(ROUND_TO_EVEN(taxed * (1 + tax_rate / 100), 2) + untaxed
                   AS DECIMAL(9,2)) total,
              IFNULL(total_paid, 0.00) total_paid
        FROM (SELECT
              txn.id, txn.type, txn.number,
              txn.created, txn.filled, txn.paid,
              txn.person,
              CONCAT(IFNULL(person.name, ''),
                     IF(person.name AND person.company, ' / ', ''),
                     IFNULL(person.company, ''))
                  AS person_name,
              SUM(ordered) * IF(txn.type = 'customer', -1, 1) AS ordered,
              SUM(allocated) * IF(txn.type = 'customer', -1, 1) AS allocated,
              CAST(ROUND_TO_EVEN(
                SUM(IF(txn_line.taxfree, 1, 0) *
                  IF(type = 'customer', -1, 1) * ordered *
                  CASE discount_type
                    WHEN 'percentage' THEN retail_price * ((100 - discount) / 100)
                    WHEN 'relative' THEN (retail_price - discount) 
                    WHEN 'fixed' THEN (discount)
                    ELSE retail_price
                  END),
                2) AS DECIMAL(9,2))
              untaxed,
              CAST(ROUND_TO_EVEN(
                SUM(IF(txn_line.taxfree, 0, 1) *
                  IF(type = 'customer', -1, 1) * ordered *
                  CASE discount_type
                    WHEN 'percentage' THEN retail_price * ((100 - discount) / 100)
                    WHEN 'relative' THEN (retail_price - discount) 
                    WHEN 'fixed' THEN (discount)
                    ELSE retail_price
                  END),
                2) AS DECIMAL(9,2))
              taxed,
              tax_rate,
              CAST((SELECT SUM(amount) FROM payment WHERE txn.id = payment.txn)
                   AS DECIMAL(9,2)) AS total_paid
         FROM txn
         LEFT JOIN txn_line ON (txn.id = txn_line.txn)
         LEFT JOIN person ON (txn.person = person.id)
        WHERE txn.id = $id) t";

  $r= $db->query($q)
    or die_query($db, $q);

  $txn= $r->fetch_assoc();
  $txn['subtotal']= (float)$txn['subtotal'];
  $txn['total']= (float)$txn['total'];
  $txn['total_paid']= (float)$txn['total_paid'];

  return $txn;
}
