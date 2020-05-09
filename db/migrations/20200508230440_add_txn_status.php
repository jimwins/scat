<?php

use Phinx\Migration\AbstractMigration;

class AddTxnStatus extends AbstractMigration
{
    public function up()
    {
      $table= $this->table('txn');
      $table
        ->addColumn('status', 'enum', [
          'values' => [
            'new',
            'filled',
            'paid',
            'processing',
            'shipped',
            'complete',
            'template',
          ],
          'default' => 'new',
          'null' => false,
          'after' => 'number',
        ])
        ->addIndex([ 'status' ])
        ->save();

      $q="UPDATE `txn`
             SET `status` = IF(`paid` IS NOT NULL AND `filled` IS NOT NULL,
                                'complete',
                                IF(`paid` IS NOT NULL, 'paid',
                                    IF(`filled` IS NOT NULL, 'filled',
                                        `status`)))";
      $count= $this->execute($q);

    }

    public function down() {
      $table= $this->table('txn');
      $table
        ->removeColumn('status')
        ->removeIndex([ 'status' ])
        ->save();
    }
}
