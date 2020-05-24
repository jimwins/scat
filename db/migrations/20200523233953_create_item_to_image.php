<?php

use Phinx\Migration\AbstractMigration;

class CreateItemToImage extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('item_to_image', [
                             'id' => false,
                             'primary_key' => ['item_id', 'image_id' ]
                           ]);
      $table
        ->addColumn('item_id', 'integer', [ 'signed' => false ])
        ->addColumn('image_id', 'integer', [ 'signed' => false ])
        ->addColumn('priority', 'integer', [
          'signed' => false,
          'default' => '0'
        ])
        ->create();

    }
}
