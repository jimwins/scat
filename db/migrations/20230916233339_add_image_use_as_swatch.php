<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class AddImageUseAsSwatch extends AbstractMigration
{
    public function change(): void
    {
      $table= $this->table('image');
      $table
        ->addColumn('use_as_swatch', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                      'null' => false,
                      'after' => 'ext',
                    ])
        ->update();

    }
}
