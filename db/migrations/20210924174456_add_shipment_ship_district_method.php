<?php

use Phinx\Migration\AbstractMigration;

class AddShipmentShipDistrictMethod extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('shipment', [ 'signed' => false ]);
      $table
        ->changeColumn('method', 'enum', [
          'values' => [
            'easypost', 'shipdistrict'
          ],
        ])
        ->update();
    }
}
