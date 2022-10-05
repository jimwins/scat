<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class AddPackagedForShippingToItem extends AbstractMigration
{
    public function change(): void
    {
      $table= $this->table('item', [ 'signed' => false ]);
      $table
        ->addColumn('packaged_for_shipping', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'null' => true,
                      'after' => 'weight'
                    ])
        ->update();
    }
}
