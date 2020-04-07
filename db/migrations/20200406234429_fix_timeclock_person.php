<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class FixTimeclockPerson extends AbstractMigration
{
    public function up()
    {
      $table= $this->table('timeclock', [ 'signed' => false ]);
      $table
        ->renameColumn('person', 'person_id')
        ->save();
    }

    public function down()
    {
      $table= $this->table('timeclock', [ 'signed' => false ]);
      $table
        ->renameColumn('person_id', 'person')
        ->save();
    }
}
