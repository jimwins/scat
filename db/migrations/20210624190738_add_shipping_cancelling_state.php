<?php

use Phinx\Migration\AbstractMigration;

class AddShippingCancellingState extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('shipment', [ 'signed' => false ]);
      $table
        ->changeColumn('status', 'enum', [
          'values' => [
            'pending',
            'unknown', 'pre_transit', 'in_transit', 'out_for_delivery',
            'delivered', 'available_for_pickup', 'return_to_sender',
            'failure', 'cancelling', 'cancelled', 'error'
          ],
          'default' => 'unknown'
        ])
        ->save();
    }
}
