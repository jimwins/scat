<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddWarningToBrand extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('brand', [ 'signed' => false ]);
      $table
        ->addColumn('warning', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                      'after' => 'description',
                    ])
        ->update();

    }
}
