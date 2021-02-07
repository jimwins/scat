<?php

use Phinx\Migration\AbstractMigration;

class AddTaxCapturedToTxn extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('txn');
      $table
        ->addColumn('tax_captured', 'datetime', [
          'null' => true,
          'after' => 'paid',
        ])
        ->save();

      $this->execute("UPDATE txn SET tax_captured = paid
                       WHERE type = 'customer'
                         AND (paid < '2021-01-01' OR online_sale_id)");

    }
}
