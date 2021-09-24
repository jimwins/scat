<?php

use Phinx\Migration\AbstractMigration;

class FixShipmentHandlingInstructions extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('shipment', [ 'signed' => false ]);
      $table
        ->changeColumn('handling_instructions', 'string', [
          'limit' => 255,
          'null' => true
        ])
        ->update();

    }
}
