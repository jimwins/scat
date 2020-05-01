<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddImageCaptionAndData extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('image');
      $table
        ->addColumn('caption', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                    ])
        ->addColumn('data', 'blob', [
                      'limit' => MysqlAdapter::BLOB_MEDIUM,
                      'null' => true,
                    ])
        ->save();
    }
}
