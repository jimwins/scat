<?php

use Phinx\Migration\AbstractMigration;

class FixPaymentTxn extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('payment', [ 'signed' => false ]);
      $table
        ->renameColumn('txn', 'txn_id')
        ->save();
    }
}
