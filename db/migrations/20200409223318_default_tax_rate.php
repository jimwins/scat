<?php

use Phinx\Migration\AbstractMigration;

class DefaultTaxRate extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('txn', [ 'signed' => false ]);
      $table
        ->changeColumn('tax_rate', 'decimal', [
                         'precision' => 9,
                         'scale' => 3,
                         'default' => 0.0,
                       ])
        ->save();
    }
}
