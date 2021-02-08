<?php

use Phinx\Migration\AbstractMigration;

class AddTxnLineReturnedFromId extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('txn_line', [ 'signed' => false ]);
      $table
        ->addColumn('returned_from_id', 'integer', [
                      'signed' => false,
                      'null' => true,
                      'after' => 'txn_id',
                    ])
        ->save();

    }
}
