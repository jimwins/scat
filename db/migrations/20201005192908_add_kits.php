<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddKits extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('kit_item', [ 'signed' => false ]);
      $table
        ->addColumn('kit_id', 'integer', [
          'signed' => false,
          'null' => false,
        ])
        ->addColumn('item_id', 'integer', [
          'signed' => false,
          'null' => false,
        ])
        ->addColumn('sort', 'integer', [
          'signed' => false,
          'null' => false,
          'default' => 0,
        ])
        ->addColumn('description', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                    ])
        ->addColumn('created', 'datetime', [
          'default' => 'CURRENT_TIMESTAMP'
        ])
        ->addColumn('modified', 'datetime', [
          'default' => 'CURRENT_TIMESTAMP',
          'update' => 'CURRENT_TIMESTAMP'
        ])
        ->addIndex(['kit_id'])
        ->create();

      $table= $this->table('item', [ 'signed' => false ]);
      $table
        ->addColumn('is_kit', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                      'after' => 'tic',
                    ])
        ->save();

      $table= $this->table('txn_line', [ 'signed' => false ]);
      $table
        ->addColumn('kit_id', 'integer', [
          'after' => 'item_id',
          'signed' => false,
          'null' => true
        ])
        ->save();
    }
}
