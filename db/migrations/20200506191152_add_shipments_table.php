<?php

use Phinx\Migration\AbstractMigration;

class AddShipmentsTable extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('shipment', [ 'signed' => false ]);
      $table
        ->addColumn('txn_id', 'integer', [
          'signed' => false,
          'null' => false,
        ])
        ->addColumn('easypost_id', 'string', [
          'limit' => 50,
          'null' => true,
         ])
        ->addColumn('status', 'enum', [
          'values' => [
            'unknown', 'pre_transit', 'in_transit', 'out_for_delivery',
            'delivered', 'available_for_pickup', 'return_to_sender',
            'failure', 'cancelled', 'error'
          ],
          'default' => 'unknown'
        ])
        ->addColumn('tracker_id', 'string', [ 'limit' => 50 ])
        ->addColumn('created', 'datetime', [
          'default' => 'CURRENT_TIMESTAMP'
        ])
        ->addColumn('modified', 'datetime', [
          'default' => 'CURRENT_TIMESTAMP',
          'update' => 'CURRENT_TIMESTAMP'
        ])
        ->create();

    }
}
