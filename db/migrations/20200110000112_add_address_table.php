<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddAddressTable extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('address', [ 'signed' => false ]);
      $table
        ->addColumn('easypost_id', 'string', [ 'limit' => 50 ])
        ->addColumn('name', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('company', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('street1', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('street2', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('city', 'string', [ 'limit' => 128, 'null' => true ])
        ->addColumn('state', 'string', [ 'limit' => 50, 'null' => true ])
        ->addColumn('zip', 'string', [ 'limit' => 10, 'null' => true ])
        ->addColumn('country', 'string', [ 'limit' => 2, 'null' => true ])
        ->addColumn('phone', 'string', [ 'limit' => 50, 'null' => true ])
        ->addColumn('email', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('residential', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addColumn('created', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('modified', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                      'update' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('active', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 1,
                    ])
        ->create();

    }
}
