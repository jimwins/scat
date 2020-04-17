<?php

use Phinx\Migration\AbstractMigration;

class FixTxnForeignKeys extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('txn', [ 'signed' => false ]);
      $table
        ->renameColumn('person', 'person_id')
        ->renameColumn('returned_from', 'returned_from_id')
        ->save();
    }
}
