<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddInternalAdFulltextIndex extends AbstractMigration
{
    public function change(): void
    {
      $table= $this->table('internal_ad', [ 'signed' => false ]);
      $table->addIndex([ 'tag', 'headline', 'caption', 'button_label' ], ['type' => 'fulltext'])
            ->update();
    }
}
