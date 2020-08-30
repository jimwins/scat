<?php

use Phinx\Migration\AbstractMigration;

class AddShippingMethod extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('shipment', [ 'signed' => false ]);
      $table
        ->addColumn('method', 'enum', [
          'values' => [
            'easypost', 'shippo'
          ],
          'after' => 'txn_id',
        ])
        ->renameColumn('easypost_id', 'method_id')
        ->update();

    }
}
