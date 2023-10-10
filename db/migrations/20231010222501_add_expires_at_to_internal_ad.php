<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddExpiresAtToInternalAd extends AbstractMigration
{
    public function change(): void
    {
      $table= $this->table('internal_ad', [ 'signed' => false ]);
      $table
        ->addColumn('expires_at', 'datetime', [
          'null' => true,
          'after' => 'button_label',
        ])
        ->update();
    }
}
