<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class AddItemDescription extends AbstractMigration
{
    public function change(): void
    {
      $table= $this->table('item', [ 'signed' => false ]);
      $table
        ->addColumn('description', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                      'after' => 'name',
                    ])
        ->update();

    }
}
