<?php

use Phinx\Migration\AbstractMigration;

class RemoveTxnNote extends AbstractMigration
{
    public function up()
    {
      $this->table('txn_note')->drop()->save();
    }

    // No down() since we never really want to recreate txn_note
}
