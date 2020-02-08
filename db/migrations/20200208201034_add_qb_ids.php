<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddQbIds extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('payment', [ 'signed' => false ]);
      $table
        ->addColumn('qb_je_id', 'string', [ 'limit' => 512 ])
        ->save();

      $table= $this->table('txn', [ 'signed' => false ]);
      $table
        ->addColumn('qb_je_id', 'string', [ 'limit' => 512 ])
        ->save();
    }
}
