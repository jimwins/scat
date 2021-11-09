<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddItemDropshipFee extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('item', [ 'signed' => false ]);
      $table
        ->addColumn('dropship_fee', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true,
                      'after' => 'no_backorder'
                    ])
        ->update();

    }
}
