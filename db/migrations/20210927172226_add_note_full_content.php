<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddNoteFullContent extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('note');
      $table
        ->addColumn('full_content', 'text', [
          'limit' => MysqlAdapter::TEXT_MEDIUM,
          'null' => true,
          'after' => 'content',
        ])
        ->update();
    }
}
