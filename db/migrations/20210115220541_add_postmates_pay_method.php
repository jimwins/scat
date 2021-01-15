<?php

use Phinx\Migration\AbstractMigration;

class AddPostmatesPayMethod extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('payment', [ 'signed' => false ]);
      $table
        ->changeColumn('method', 'enum', [
                       'values' => [
                         'cash', 'change', 'credit', 'square', 'stripe',
                         'gift', 'check', 'dwolla', 'paypal', 'amazon',
                         'eventbrite', 'venmo', 'loyalty', 'postmates',
                         'discount', 'withdrawal', 'bad', 'donation',
                         'internal'
                       ],
                     ])
        ->save();
    }
}
