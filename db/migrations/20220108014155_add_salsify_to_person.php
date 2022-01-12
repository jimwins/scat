<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddSalsifyToPerson extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('person');
      $table
        ->addColumn('salsify_url', 'string', [
                      'limit' => 255,
                      'null' => true,
                      'after' => 'vendor_rebate',
                    ])
        ->update();
    }
}
