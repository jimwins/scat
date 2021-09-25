<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddPersonGiftCard extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('person', [ 'signed' => false ]);
      $table
        ->addColumn('giftcard_id', 'integer', [
          'signed' => false,
          'null' => true,
          'after' => 'vendor_rebate',
        ])
        ->update();

    }
}
