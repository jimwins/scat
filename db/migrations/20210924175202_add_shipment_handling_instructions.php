<?php

use Phinx\Migration\AbstractMigration;

class AddShipmentHandlingInstructions extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('shipment', [ 'signed' => false ]);
      $table
        ->addColumn('handling_instructions', 'string', [
          'limit' => 255,
          'after' => 'insurance'
        ])
        ->update();

    }
}
