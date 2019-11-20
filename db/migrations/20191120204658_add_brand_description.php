<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddBrandDescription extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('brand', [ 'signed' => false ]);
      $table
        ->addColumn('description', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                    ])
        ->update();
    }
}
