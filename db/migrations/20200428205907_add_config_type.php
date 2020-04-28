<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddConfigType extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('config');
      $table
        ->addColumn('type', 'enum', [
                      'values' => [ 'string', 'text', 'blob' ],
                      'default' => 'string'
                    ])
        /* Don't use ->addTimestamps() because we use DATETIME */
        ->addColumn('created_at', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addColumn('updated_at', 'datetime', [
                      'update' => 'CURRENT_TIMESTAMP',
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addIndex(['created_at'])
        ->save();
    }
}
