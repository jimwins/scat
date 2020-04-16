<?php

use Phinx\Migration\AbstractMigration;

class FixVendorItemForeignKeys extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('vendor_item', [ 'signed' => false ]);
      $table
        ->renameColumn('vendor', 'vendor_id')
        ->renameColumn('item', 'item_id')
        ->save();
    }
}
