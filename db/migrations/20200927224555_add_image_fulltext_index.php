<?php

use Phinx\Migration\AbstractMigration;

class AddImageFulltextIndex extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('image', [ 'signed' => false ]);
      $table
        ->addIndex([ 'name', 'alt_text', 'caption' ], [ 'type' => 'fulltext' ])
        ->save();
    }
}
