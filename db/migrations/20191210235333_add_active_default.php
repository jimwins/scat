<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddActiveDefault extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('brand', [ 'signed' => false ]);
      $table
        ->changeColumn('active', 'integer', [
                          'signed' => 'false',
                          'limit' => MysqlAdapter::INT_TINY,
                          'default' => 1,
                        ])
        ->save();

      $table= $this->table('department', [ 'signed' => false ]);
      $table
        ->changeColumn('active', 'integer', [
                          'signed' => 'false',
                          'limit' => MysqlAdapter::INT_TINY,
                          'default' => 1,
                        ])
        ->save();

      $table= $this->table('item', [ 'signed' => false ]);
      $table
        ->changeColumn('active', 'integer', [
                          'signed' => 'false',
                          'limit' => MysqlAdapter::INT_TINY,
                          'default' => 1,
                        ])
        ->save();
    }
}
