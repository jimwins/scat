<?php

use Phinx\Migration\AbstractMigration;

class AddMediaB2FileId extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('image');
      $table
        ->addColumn('b2_file_id', 'string', [
          'limit' => 200,
          'default' => '',
          'after' => 'uuid',
        ])
        ->update();

    }
}
