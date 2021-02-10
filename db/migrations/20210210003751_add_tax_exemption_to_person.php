<?php

use Phinx\Migration\AbstractMigration;

class AddTaxExemptionToPerson extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('person');
      $table
        ->addColumn('exemption_certificate_id', 'string', [
                      'null' => true,
                      'limit' => 50,
                      'after' => 'tax_id',
                    ])
        ->save();

    }
}
