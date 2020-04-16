<?php

use Phinx\Migration\AbstractMigration;

class BarcodeItemIdAndDates extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('barcode', [ 'signed' => false ]);
      $table
        ->renameColumn('item', 'item_id')
        /* Don't use ->addTimestamps() because we use DATETIME */
        ->addColumn('created_at', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addColumn('updated_at', 'datetime', [
                      'update' => 'CURRENT_TIMESTAMP',
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addIndex(['created_at'])
        ->save();
    }
}
