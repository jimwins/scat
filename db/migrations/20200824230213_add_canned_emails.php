<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddCannedEmails extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('canned_message', [ 'signed' => false ]);
      $table
        ->addColumn('slug', 'string', [ 'limit' => 50 ])
        ->addColumn('subject', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('content', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                    ])
        /* Ideally would be same enum as txn table, but oh well */
        ->addColumn('new_status', 'string', [ 'limit' => 50 ])
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
