<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddCapturedToPayment extends AbstractMigration
{
    public function change(): void
    {
      $table= $this->table('payment', [ 'signed' => false ]);
      $table
        ->addColumn('captured', 'datetime', [ 'null' => true ])
        ->update();

      // Make captured be the same as processed for existing payments
      $this->execute("UPDATE payment SET captured = processed");
    }
}
