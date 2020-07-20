<?php

use Phinx\Migration\AbstractMigration;

class AddMoreTxnStatus extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('txn');
      $table
        ->addColumn('status', 'enum', [
          'values' => [
            'new',
            'filled',
            'paid',
            'processing',
            'waitingforitems',
            'readyforpickup',
            'shipping',
            'shipped',
            'complete',
            'template',
          ],
          'default' => 'new',
          'null' => false,
          'after' => 'number',
        ])
        ->addIndex([ 'status' ])
        ->save();

    }
}
