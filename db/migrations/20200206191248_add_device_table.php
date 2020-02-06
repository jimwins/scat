<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddDeviceTable extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('device', [ 'signed' => false ]);
      $table
        ->addColumn('person_id', 'integer', [
                      'signed' => 'false',
                      'null' => 'true'
                    ])
        ->addColumn('token', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('created', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('modified', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                      'update' => 'CURRENT_TIMESTAMP'
                    ])
        ->create();
    }
}
