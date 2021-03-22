<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddInternalAds extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('internal_ad', [ 'signed' => false ]);
      $table
        ->addColumn('link_type', 'enum', [
          'values' => [ 'item', 'product', 'url' ],
          'null' => false,
        ])
        ->addColumn('link_id', 'integer', [
          'signed' => false,
          'null' => true,
        ])
        ->addColumn('link_url', 'string', [
          'limit' => 255,
          'null' => true,
        ])
        ->addColumn('image_id', 'integer', [
          'signed' => false,
          'null' => false,
        ])
        ->addColumn('tag', 'string', [ 'limit' => 50 ])
        ->addColumn('headline', 'string', [ 'limit' => 255 ])
        ->addColumn('caption', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                    ])
        ->addColumn('button_label', 'string', [ 'limit' => 50 ])
        ->addColumn('created_at', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('updated_at', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                      'update' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('active', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 1,
                    ])
        ->create();

      $table= $this->table('department_to_internal_ad', [ 'signed' => false ]);
      $table
        ->addColumn('department_id', 'integer', [ 'signed' => false ])
        ->addColumn('internal_add_id', 'integer', [ 'signed' => false ])
        ->addColumn('priority', 'integer', [
          'signed' => false,
          'default' => '0'
        ])
        ->addColumn('created_at', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('updated_at', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                      'update' => 'CURRENT_TIMESTAMP'
                    ])
        ->create();

      $table= $this->table('product_to_internal_ad', [ 'signed' => false ]);
      $table
        ->addColumn('product_id', 'integer', [ 'signed' => false ])
        ->addColumn('internal_add_id', 'integer', [ 'signed' => false ])
        ->addColumn('priority', 'integer', [
          'signed' => false,
          'default' => '0'
        ])
        ->addColumn('created_at', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('updated_at', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                      'update' => 'CURRENT_TIMESTAMP'
                    ])
        ->create();

    }
}
