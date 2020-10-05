<?php

use Phinx\Migration\AbstractMigration;

class AddKitQuantity extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('kit_item', [ 'signed' => false ]);
      $table
        ->addColumn('quantity', 'integer', [
          'after' => 'item_id',
          'signed' => false,
          'null' => false,
          'default' => 1,
        ])
        ->save();

    }
}
