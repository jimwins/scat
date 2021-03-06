<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddNoBackorderToItem extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('item', [ 'signed' => false ]);
      $table
        ->addColumn('no_backorder', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'null' => true
                    ])
        ->update();

    }
}
