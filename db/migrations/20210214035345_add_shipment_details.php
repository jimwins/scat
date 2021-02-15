<?php

use Phinx\Migration\AbstractMigration;

class AddShipmentDetails extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('shipment');
      $table
        ->addColumn('length', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true,
                      'after' => 'tracker_id'
                    ])
        ->addColumn('width', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true,
                      'after' => 'length'
                    ])
        ->addColumn('height', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true,
                      'after' => 'width'
                    ])
        ->addColumn('weight', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true,
                      'after' => 'height'
                    ])
        ->addColumn('carrier', 'string', [
                      'limit' => 50,
                      'null' => true,
                      'after' => 'weight'
                    ])
        ->addColumn('service', 'string', [
                      'limit' => 50,
                      'null' => true,
                      'after' => 'carrier'
                    ])
        ->addColumn('rate', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true,
                      'after' => 'service'
                    ])
        ->addColumn('insurance', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true,
                      'after' => 'rate'
                    ])
        ->save();

    }
}
