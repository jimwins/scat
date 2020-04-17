<?php

use Phinx\Migration\AbstractMigration;

class FixTxnLineForeignKeys extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('txn_line', [ 'signed' => false ]);
      $table
        ->renameColumn('txn', 'txn_id')
        ->renameColumn('item', 'item_id')
        ->save();
    }
}
