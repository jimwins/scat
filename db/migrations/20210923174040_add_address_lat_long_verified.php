<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddAddressLatLongVerified extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('address', [ 'signed' => false ]);
      $table
        ->addColumn('latitude', 'decimal', [
          'precision' => 9,
          'scale' => 5,
          'default' => '0.0',
          'null' => true,
          'after' => 'residential',
        ])
        ->addColumn('longitude', 'decimal', [
          'precision' => 9,
          'scale' => 5,
          'default' => '0.0',
          'null' => true,
          'after' => 'latitude',
        ])
        ->addColumn('verified', 'integer', [
          'limit' => MysqlAdapter::INT_TINY,
          'default' => 0,
          'after' => 'longitude',
        ])
        ->update();
    }
}
