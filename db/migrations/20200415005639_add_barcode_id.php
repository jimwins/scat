<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddBarcodeId extends AbstractMigration
{
    public function up()
    {
      $q= "ALTER TABLE barcode DROP PRIMARY KEY,
                               ADD COLUMN `id` INT(11) unsigned NOT NULL
                                   AUTO_INCREMENT PRIMARY KEY FIRST,
                               ADD UNIQUE `item` (item, code)";
      $this->execute($q);
    }

    public function down()
    {
      $q= "ALTER TABLE barcode DROP PRIMARY KEY, DROP COLUMN `id`,
                               DROP KEY `item`,
                               ADD PRIMARY KEY (item, code)";
    }
}
