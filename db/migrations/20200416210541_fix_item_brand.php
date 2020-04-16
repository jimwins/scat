<?php

use Phinx\Migration\AbstractMigration;

class FixItemBrand extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('item', [ 'signed' => false ]);
      $table
        ->renameColumn('brand', 'brand_id')
        ->save();
    }
}
