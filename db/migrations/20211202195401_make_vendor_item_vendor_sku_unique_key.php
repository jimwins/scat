<?php

use Phinx\Migration\AbstractMigration;

class MakeVendorItemVendorSkuUniqueKey extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('vendor_item', [ 'signed' => false ]);
      $table
        ->addIndex(['vendor_id','vendor_sku'], [ 'unique' => true ])
        ->update();
    }
}
