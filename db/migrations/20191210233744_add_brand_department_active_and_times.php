<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddBrandDepartmentActiveAndTimes extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('brand', [ 'signed' => false ]);
      $table
        /* Don't use ->addTimestamps() because we use DATETIME */
        ->addColumn('created_at', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addColumn('updated_at', 'datetime', [
                      'update' => 'CURRENT_TIMESTAMP',
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addColumn('active', 'integer', [
                      'signed' => 'false',
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addIndex(['created_at'])
        ->save();

      $table= $this->table('department', [ 'signed' => false ]);
      $table
        /* Don't use ->addTimestamps() because we use DATETIME */
        ->addColumn('created_at', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addColumn('updated_at', 'datetime', [
                      'update' => 'CURRENT_TIMESTAMP',
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addColumn('active', 'integer', [
                      'signed' => 'false',
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addIndex(['created_at'])
        ->save();
    }
}
