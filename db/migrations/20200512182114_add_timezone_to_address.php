<?php

use Phinx\Migration\AbstractMigration;

class AddTimezoneToAddress extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('address');
      $table
        ->addColumn('timezone', 'string', [
          'limit' => 128,
          'null' => true,
          'default' => null,
          'after' => 'phone',
        ])
        ->save();

    }
}
