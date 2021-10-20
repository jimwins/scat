<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddProductImportance extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('product', [ 'signed' => false ]);
      $table
        ->addColumn('importance', 'integer', [
                      'signed' => 'false',
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                      'after' => 'slug',
                    ])
        ->removeColumn('variation_style')
        ->update();
    }
}
