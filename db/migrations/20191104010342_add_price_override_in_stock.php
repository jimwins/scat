<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddPriceOverrideInStock extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('price_override', [ 'signed' => false ]);
      $table
        ->addColumn('in_stock', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                      'after' => 'minimum_quantity',
                    ])
        ->update();
    }
}
