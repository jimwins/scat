<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class InitialFunctions extends AbstractMigration
{
    public function up() {
      $query= <<<EOF
CREATE FUNCTION
ROUND_TO_EVEN(val DECIMAL(32,16), places INT)
RETURNS DECIMAL(32,16) DETERMINISTIC
BEGIN
  RETURN IF(ABS(val - TRUNCATE(val, places)) * POWER(10, places + 1) = 5 
            AND NOT CONVERT(TRUNCATE(ABS(val) * POWER(10, places), 0),
                            UNSIGNED) % 2 = 1,
            TRUNCATE(val, places), ROUND(val, places));
END
EOF;
      $this->execute($query);

      $query= <<<EOF
CREATE FUNCTION
SALE_PRICE(retail_price DECIMAL(9,2), type CHAR(32), discount DECIMAL(9,2))
RETURNS DECIMAL(9,2) DETERMINISTIC
BEGIN
  RETURN IF(type IS NOT NULL AND type != '',
            CASE type
            WHEN 'percentage' THEN
              CAST(ROUND_TO_EVEN(retail_price * ((100 - discount) / 100), 2)
                   AS DECIMAL(9,2))
            WHEN 'relative' THEN 
              (retail_price - discount)
            WHEN 'fixed' THEN
              (discount)
            END,
            retail_price); 
END
EOF;
      $this->execute($query);
    }
}
