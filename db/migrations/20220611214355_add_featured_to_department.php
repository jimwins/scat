<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class AddFeaturedToDepartment extends AbstractMigration
{
    public function change(): void
    {
      $table= $this->table('department', [ 'signed' => false ]);
      $table
        ->addColumn('featured', 'integer', [
                      'signed' => 'false',
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                      'after' => 'slug',
                    ])
        ->update();
    }
}
