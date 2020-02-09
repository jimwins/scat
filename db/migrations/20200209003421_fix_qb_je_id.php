<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class FixQbJeId extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('payment', [ 'signed' => false ]);
      $table
        ->changeColumn('qb_je_id', 'string', [ 'limit' => 512, 'default' => '' ])
        ->save();

      $table= $this->table('txn', [ 'signed' => false ]);
      $table
        ->changeColumn('qb_je_id', 'string', [ 'limit' => 512, 'default' => '' ])
        ->save();
    }
}
