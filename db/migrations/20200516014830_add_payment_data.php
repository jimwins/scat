<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddPaymentData extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('payment');
      $table
        ->addColumn('data', 'blob', [
                      'limit' => MysqlAdapter::BLOB_MEDIUM,
                      'null' => true,
                    ])
        ->save();
    }
}
