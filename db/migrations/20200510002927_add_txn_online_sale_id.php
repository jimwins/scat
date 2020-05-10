<?php

use Phinx\Migration\AbstractMigration;

class AddTxnOnlineSaleId extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('txn');
      $table
        ->addColumn('online_sale_id', 'integer', [
          'signed' => false,
          'null' => true,
          'after' => 'uuid',
        ])
        ->save();

    }
}
