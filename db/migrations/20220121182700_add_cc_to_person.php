<?php

use Phinx\Migration\AbstractMigration;

class AddCcToPerson extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('person');
      $table
        ->addColumn('cc_email', 'string', [
                      'limit' => 255,
                      'null' => true,
                      'after' => 'salsify_url',
                    ])
        ->update();
    }
}
