<?php

use Phinx\Migration\AbstractMigration;

class AddVenmoPayMethod extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('payment');
      $table
        ->changeColumn('method', 'enum', [
                       'values' => [
                         'cash', 'change', 'credit', 'square', 'stripe',
                         'gift', 'check', 'dwolla', 'paypal', 'amazon',
                         'eventbrite', 'venmo',
                         'discount', 'withdrawal', 'bad', 'donation',
                         'internal'
                       ],
                     ])
        ->save();
    }
}
