<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddGoogleProductCategories extends AbstractMigration
{
    public function change(): void
    {
      $table= $this->table('google_product_category', [ 'signed' => false ]);
      $table
        ->addColumn('name', 'string', [ 'limit' => 255 ])
        ->addIndex(['name'], [ 'unique' => true ])
        ->create();

      $table= $this->table('item', [ 'signed' => false ]);
      $table
        ->addColumn('google_product_category_id', 'integer', [
                      'signed' => false,
                      'default' => 0,
                      'after' => 'tic',
                    ])
        ->update();
    }
}
