<?php

use Phinx\Migration\AbstractMigration;

class AddBrandFulltextIndex extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('brand', [ 'signed' => false ]);
      $table
        ->addIndex([ 'name', 'slug' ], [ 'type' => 'fulltext' ])
        ->save();
    }
}
