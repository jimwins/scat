<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddAddressToTxn extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('txn', [ 'signed' => false ]);
      $table
        ->addColumn('shipping_address_id', 'integer', [
                      'signed' => 'false',
                      'null' => 'true',
                      'after' => 'person',
                    ])
        ->save();
    }
}
