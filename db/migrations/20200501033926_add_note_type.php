<?php

use Phinx\Migration\AbstractMigration;

class AddNoteType extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('note');
      $table
        ->addColumn('source', 'enum', [
          'values' => [ 'internal', 'sms', 'email' ],
          'default' => 'internal',
          'null' => true,
          'after' => 'attach_id',
        ])
        ->save();
    }
}
