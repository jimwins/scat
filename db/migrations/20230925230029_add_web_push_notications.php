<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class AddWebPushNotications extends AbstractMigration
{
    public function change(): void
    {
      $table= $this->table('web_push_subscription', [ 'signed' => false ]);
      $table
        ->addColumn('endpoint', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('data', 'blob', [
                      'limit' => MysqlAdapter::BLOB_MEDIUM,
                      'null' => true,
                    ])
        ->addColumn('created_at', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('updated_at', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                      'update' => 'CURRENT_TIMESTAMP'
                    ])
        ->create();

    }
}
