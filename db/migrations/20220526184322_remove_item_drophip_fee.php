<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveItemDrophipFee extends AbstractMigration
{
    public function change(): void
    {
      $table= $this->table('item', [ 'signed' => false ]);
      $table
        ->removeColumn('dropship_fee')
        ->update();
    }
}
