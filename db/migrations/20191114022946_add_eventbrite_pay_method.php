<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddEventbritePayMethod extends AbstractMigration
{
    public function up()
    {
      $table= $this->table('payment', [ 'signed' => false ]);
      $table
        ->changeColumn('method', 'enum', [
                       'values' => [
                         'cash', 'change', 'credit', 'square', 'stripe',
                         'gift', 'check', 'dwolla', 'paypal', 'amazon',
                         'eventbrite',
                         'discount', 'withdrawal', 'bad', 'donation',
                         'internal'
                       ],
                     ])
        ->save();
    }

    public function down() {
      // We don't actually undo this, no harm in leaving it
    }
}
