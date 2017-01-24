<?
require '../scat.php';

$fn= $_FILES['src']['tmp_name'];

if (!$fn)
  die_jsonp("No file uploaded");

#Date,Time,Activity Type,Activity,Name,Notes,Phone,Store,Terminal ID,Suspicious

$q= "CREATE TEMPORARY TABLE loyalty_import (
  processed datetime,
  activity varchar(255),
  points int,
  name varchar(255),
  phone varchar(255)
)";

$db->start_transaction();

$db->query($q)
  or die_query($db, $q);

$q= "LOAD DATA LOCAL INFILE '$fn'
          INTO TABLE loyalty_import
        FIELDS TERMINATED BY ','
        OPTIONALLY ENCLOSED BY '\"'
        IGNORE 1 LINES
        (@date, @time, activity, points, name, @notes, phone,
         @store, @terminal_id, @suspicious)
      SET processed = STR_TO_DATE(CONCAT(@date, ' ', @time),
                                  '%m/%d/%Y %h:%i %p')";
$db->query($q)
  or die_query($db, $q);

echo "Loaded {$db->affected_rows} rows.";

$q= "INSERT INTO person (name, phone, loyalty_number, active)
     SELECT IF(name LIKE 'User %', '', name) name,
            phone, phone loyalty_number, 1 active
       FROM loyalty_import
         ON DUPLICATE KEY
            UPDATE name= IF(person.name = '' AND VALUES(name) NOT LIKE 'User %',
                            VALUES(name),
                            person.name)";

$db->query($q)
  or die_query($db, $q);

$q= "INSERT INTO loyalty (person_id, points, processed, note)
     SELECT id, points, processed, activity
       FROM loyalty_import
       JOIN person ON loyalty_import.phone = loyalty_number";

$db->query($q)
  or die_query($db, $q);

$db->commit();
