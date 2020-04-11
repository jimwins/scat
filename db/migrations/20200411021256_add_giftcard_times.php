<?php

use Phinx\Migration\AbstractMigration;

class AddGiftcardTimes extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('giftcard', [ 'signed' => false ]);
      $table
        /* Don't use ->addTimestamps() because we use DATETIME */
        ->addColumn('created_at', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addColumn('updated_at', 'datetime', [
                      'update' => 'CURRENT_TIMESTAMP',
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addIndex(['created_at'])
        ->save();

      $this->execute("UPDATE giftcard SET
                        created_at = IFNULL((SELECT MIN(entered)
                                               FROM giftcard_txn
                                              WHERE card_id = giftcard.id),
                                            created_at),
                        updated_at= IFNULL((SELECT MAX(entered)
                                              FROM giftcard_txn
                                             WHERE card_id = giftcard.id),
                                           updated_at)");
    }
}
