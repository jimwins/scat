<?php

use Phinx\Migration\AbstractMigration;

class ConfigTypePassword extends AbstractMigration
{
    public function up()
    {
      $table= $this->table('config');
      $table
        ->changeColumn('type', 'enum', [
                        'values' => [ 'string', 'password', 'text', 'blob' ],
                        'default' => 'string'
                      ])
        ->save();
    }

    public function down() {
      // We don't actually undo this, no harm in leaving it
    }
}
