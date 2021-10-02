<?php

use Phinx\Migration\AbstractMigration;

class FixTimeclockAuditEntry extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('timeclock_audit', [ 'signed' => false ]);
      $table
        ->renameColumn('entry', 'timeclock_id')
        ->update();
    }
}
