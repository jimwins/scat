<?php

use Phinx\Migration\AbstractMigration;

class LongerImageUuid extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('image', [ 'signed' => false ]);
      $table
        ->changeColumn('uuid', 'string', [ 'limit' => 255 ])
        ->update();
    }
}
