<?php

use Phinx\Migration\AbstractMigration;

class AddImagePublitioId extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('image');
      $table
        ->addColumn('publitio_id', 'string', [
                      'limit' => 12,
                      'default' => '',
                      'null' => true,
                      'after' => 'uuid',
                    ])
        ->save();
    }
}
