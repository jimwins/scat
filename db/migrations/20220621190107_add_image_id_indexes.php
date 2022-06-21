<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddImageIdIndexes extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
      $table= $this->table('item_to_image');
      $table
        ->addIndex(['image_id'])
        ->update();

      $table= $this->table('product_to_image');
      $table
        ->addIndex(['image_id'])
        ->update();

      $table= $this->table('internal_ad');
      $table
        ->addIndex(['image_id'])
        ->update();

    }
}
