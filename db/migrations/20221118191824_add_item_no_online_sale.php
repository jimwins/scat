<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class AddItemNoOnlineSale extends AbstractMigration
{
    public function change(): void
    {
      $table= $this->table('item', [ 'signed' => false ]);
      $table
        ->addColumn(
          'no_online_sale', 'integer', [
          'limit' => MysqlAdapter::INT_TINY,
          'null' => true,
          'after' => 'no_backorder',
        ])
        ->update();
    }
}
