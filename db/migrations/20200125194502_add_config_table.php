<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddConfigTable extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('config', [ 'signed' => false ]);
      $table
        ->addColumn('name', 'string', [ 'limit' => 255 ])
        ->addColumn('value', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                    ])
        ->addIndex(['name'], [ 'unique' => true ])
        ->create();
    }
}
