<?php

use Phinx\Migration\AbstractMigration;

class FixShipmentIdFields extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('shipment');
      $table
        ->changeColumn('tracker_id', 'string', [
          'limit' => 50,
          'null' => true,
        ])
        ->save();

    }
}
