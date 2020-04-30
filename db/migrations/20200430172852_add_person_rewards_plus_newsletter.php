<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddPersonRewardsPlusNewsletter extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('person');
      $table
        ->removeColumn('payment_account_id')
        ->removeColumn('sms_ok')
        ->removeColumn('email_ok')
        ->addColumn('mailerlite_id', 'string', [
                      'limit' => 32,
                      'null' => true,
                      'after' => 'email',
                    ])
        ->addColumn('preferred_contact', 'enum', [
                      'values' => [ 'any', 'call', 'text', 'email', 'none' ],
                      'null' => true,
                      'default' => 'any',
                      'after' => 'phone',
                    ])
        ->addColumn('rewardsplus', 'integer', [
                      'signed' => 'false',
                      'limit' => MysqlAdapter::INT_TINY,
                      'null' => true,
                      'default' => 0,
                      'after' => 'suppress_loyalty',
                    ])
        ->addColumn('subscriptions', 'json', [
                      'null' => true,
                      'after' => 'mailerlite_id',
                    ])
        ->save();
    }
}
