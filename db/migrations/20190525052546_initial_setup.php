<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class InitialSetup extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('barcode', [
                             'id' => false,
                             'primary_key' => ['item', 'code' ]
                           ]);
      $table
        ->addColumn('code', 'string', [ 'limit' => 255 ])
        ->addColumn('item', 'integer', [ 'signed' => false ])
        ->addColumn('quantity', 'integer', [
                      'signed' => false,
                      'default' => 1
                    ])
        ->addIndex(['code'])
        ->create();

      $table= $this->table('brand', [ 'signed' => false ]);
      $table
        ->addColumn('name', 'string', [ 'limit' => 128 ])
        ->addColumn('slug', 'string', [ 'limit' => 255 ])
        ->addIndex(['name'], [ 'unique' => true ])
        ->create();

      $table= $this->table('cc_trace', [ 'signed' => false ]);
      $table
        ->addColumn('traced', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ])
        ->addColumn('txn_id', 'integer', [ 'signed' => false, 'null' => true ])
        ->addColumn('payment_id', 'integer', [
                      'signed' => false,
                      'null' => true
                    ])
        ->addColumn('request', 'blob', [
                      'limit' => MysqlAdapter::BLOB_MEDIUM,
                      'null' => true,
                    ])
        ->addColumn('response', 'blob', [
                      'limit' => MysqlAdapter::BLOB_MEDIUM,
                      'null' => true,
                    ])
        ->addColumn('info', 'blob', [
                      'limit' => MysqlAdapter::BLOB_MEDIUM,
                      'null' => true,
                    ])
        ->create();

      $table= $this->table('department', [ 'signed' => false ]);
      $table
        ->addColumn('parent_id', 'integer', [
                      'signed' => false,
                      'default' => 0
                    ])
        ->addColumn('name', 'string', [ 'limit' => 255 ])
        ->addColumn('slug', 'string', [ 'limit' => 80 ])
        ->addColumn('description', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                    ])
        ->addIndex(['parent_id','slug'], [ 'unique' => true ])
        ->create();

      $table= $this->table('giftcard', [ 'signed' => false ]);
      $table
        ->addColumn('pin', 'string', [ 'limit' => 4 ])
        ->addColumn('active', 'integer', [
                      'signed' => 'false',
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addColumn('expires', 'datetime', [ 'null' => true ])
        ->create();

      $table= $this->table('giftcard_txn', [ 'signed' => false ]);
      $table
        ->addColumn('card_id', 'integer', [ 'signed' => false ])
        ->addColumn('entered', 'datetime', [ 'default' => 'CURRENT_TIMESTAMP' ])
        ->addColumn('amount', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true
                    ])
        ->addColumn('txn_id', 'integer', [ 'signed' => false, 'null' => true ])
        ->addIndex(['txn_id'])
        ->create();

      $table= $this->table('image', [ 'signed' => false ]);
      $table
        ->addColumn('uuid', 'string', [ 'limit' => 50 ])
        ->addColumn('name', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('alt_text', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                    ])
        ->addColumn('width', 'integer', [ 'signed' => false, 'null' => true ])
        ->addColumn('height', 'integer', [ 'signed' => false, 'null' => true ])
        ->addColumn('ext', 'string', [ 'limit' => 10, 'null' => true ])
        ->addIndex(['uuid'], [ 'unique' => true ])
        ->create();

      $table= $this->table('item', [ 'signed' => false ]);
      $table
        ->addColumn('product_id', 'integer', [
                      'signed' => false,
                      'default' => 0
                    ])
        ->addColumn('code', 'string', [ 'limit' => 100 ])
        ->addColumn('name', 'string', [ 'limit' => 255 ])
        ->addColumn('short_name', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('variation', 'string', [ 'limit' => 255, 'default' => '' ])
        ->addColumn('brand', 'integer', [
                      'signed' => false,
                      'null' => true
                    ])
        ->addColumn('retail_price', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'default' => '0.00'
                    ])
        ->addColumn('discount_type', 'enum', [
                      'values' => [ 'percentage', 'relative', 'fixed' ],
                      'null' => true
                    ])
        ->addColumn('discount', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true
                    ])
        ->addColumn('taxfree', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addColumn('minimum_quantity', 'integer', [
                      'signed' => false,
                      'default' => 1,
                    ])
        ->addColumn('purchase_quantity', 'integer', [
                      'signed' => false,
                      'default' => 1,
                    ])
        ->addColumn('length', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true
                    ])
        ->addColumn('width', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true
                    ])
        ->addColumn('height', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true
                    ])
        ->addColumn('weight', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true
                    ])
        ->addColumn('color', 'string', [ 'limit' => 6, 'null' => true ])
        ->addColumn('tic', 'char', [ 'limit' => 5, 'default' => '00000' ])
        ->addColumn('active', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                    ])
        ->addColumn('deleted', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0
                    ])
        ->addColumn('reviewed', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0
                    ])
        ->addColumn('added', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('modified', 'datetime', [
                      'null' => true,
                      'update' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('inventoried', 'datetime', [
                      'null' => true,
                    ])
        ->addColumn('prop65', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'null' => true
                    ])
        ->addColumn('oversized', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'null' => true
                    ])
        ->addColumn('hazmat', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'null' => true
                    ])
        ->addIndex(['code'], [ 'unique' => true ])
        ->addIndex(['product_id'])
        ->addIndex(['brand'])
        ->addIndex(['inventoried'])
        ->create();

      $table= $this->table('loyalty', [ 'signed' => false ]);
      $table
        ->addColumn('person_id', 'integer', [
                      'signed' => false,
                    ])
        ->addColumn('points', 'integer', [
                      'null' => true,
                      'default' => 0,
                    ])
        ->addColumn('txn_id', 'integer', [
                      'signed' => false,
                      'default' => 0,
                    ])
        ->addColumn('processed', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addColumn('note', 'string', [ 'limit' => 255, 'default' => '' ])
        ->addIndex(['person_id'])
        ->create();

      $table= $this->table('loyalty_reward', [ 'signed' => false ]);
      $table
        ->addColumn('cost', 'integer', [
                      'default' => 0,
                    ])
        ->addColumn('item_id', 'integer', [
                      'signed' => false,
                    ])
        ->create();

      $table= $this->table('note', [ 'signed' => false ]);
      $table
        ->addColumn('kind', 'enum', [
                      'values' => [ 'general', 'txn', 'person', 'item' ],
                      'default' => 'general'
                    ])
        ->addColumn('attach_id', 'integer', [
                      'signed' => false,
                      'null' => true,
                    ])
        ->addColumn('content', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                    ])
        ->addColumn('added', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('modified', 'datetime', [
                      'null' => true,
                      'update' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('person_id', 'integer', [
                      'signed' => false,
                      'default' => 0,
                    ])
        ->addColumn('parent_id', 'integer', [
                      'signed' => false,
                      'default' => 0,
                    ])
        ->addColumn('public', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addColumn('todo', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addIndex(['person_id'])
        ->addIndex(['parent_id','id'])
        ->addIndex(['kind','attach_id'])
        ->create();

      $table= $this->table('payment', [ 'signed' => false ]);
      $table
        ->addColumn('txn', 'integer', [
                      'signed' => false,
                    ])
        ->addColumn('method', 'enum', [
                      'values' => [
                        'cash', 'change', 'credit', 'square', 'stripe',
                        'gift', 'check', 'dwolla', 'paypal', 'amazon',
                        'discount', 'withdrawal', 'bad', 'donation',
                        'internal'
                      ],
                    ])
        ->addColumn('amount', 'decimal', [
                      'precision' => 9,
                      'scale' => 3,
                    ])
        ->addColumn('cc_txn', 'string', [ 'limit' => 32, 'null' => true ])
        ->addColumn('cc_refid', 'string', [ 'limit' => 32, 'null' => true ])
        ->addColumn('cc_approval', 'string', [ 'limit' => 30, 'null' => true ])
        ->addColumn('cc_lastfour', 'string', [ 'limit' => 4, 'null' => true ])
        ->addColumn('cc_expire', 'string', [ 'limit' => 4, 'null' => true ])
        ->addColumn('cc_type', 'string', [ 'limit' => 32, 'null' => true ])
        ->addColumn('cc_sign', 'blob', [
                      'limit' => MysqlAdapter::BLOB_MEDIUM,
                      'null' => true,
                    ])
        ->addColumn('cc_receipt', 'blob', [
                      'limit' => MysqlAdapter::BLOB_MEDIUM,
                      'null' => true,
                    ])
        ->addColumn('discount', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true
                    ])
        ->addColumn('processed', 'datetime')
        ->addIndex(['txn'])
        ->addIndex(['processed'])
        ->create();

      $table= $this->table('person', [ 'signed' => false ]);
      $table
        ->addColumn('role', 'enum', [
                      'values' => [
                        'customer', 'employee', 'vendor'
                      ],
                      'default' => 'customer',
                      'null' => true,
                    ])
        ->addColumn('name', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('company', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('address', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                    ])
        ->addColumn('email', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('phone', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('loyalty_number', 'string', [
                      'limit' => 32,
                      'null' => true
                    ])
        ->addColumn('suppress_loyalty', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addColumn('sms_ok', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'null' => true,
                      'default' => 0,
                    ])
        ->addColumn('email_ok', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'null' => true,
                      'default' => 0,
                    ])
        ->addColumn('birthday', 'date', [ 'null' => true ])
        ->addColumn('url', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('instagram', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('notes', 'text', [
                      'limit' => MysqlAdapter::TEXT_LONG,
                      'null' => true,
                    ])
        ->addColumn('tax_id', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('payment_account_id', 'string', [
                      'limit' => 50,
                      'null' => true
                    ])
        ->addColumn('vendor_rebate', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'default' => '0.00'
                    ])
        ->addColumn('active', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 1,
                    ])
        ->addColumn('deleted', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addColumn('created', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('modified', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                      'update' => 'CURRENT_TIMESTAMP'
                    ])
        ->addIndex(['loyalty_number'], [ 'unique' => true ])
        ->create();


      $table= $this->table('price_override', [ 'signed' => false ]);
      $table
        ->addColumn('pattern', 'string', [ 'limit' => 255 ])
        ->addColumn('pattern_type', 'enum', [
                      'values' => [
                        'like', 'rlike', 'product'
                      ],
                      'default' => 'like'
                    ])
        ->addColumn('minimum_quantity', 'integer', [
                      'signed' => false,
                      'default' => 1
                    ])
        ->addColumn('discount_type', 'enum', [
                      'values' => [
                        'percentage', 'additional_percentage',
                        'relative', 'fixed'
                      ],
                      'null' => true,
                    ])
        ->addColumn('discount', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true
                    ])
        ->addColumn('expires', 'datetime', [ 'null' => true ])
        ->addColumn('added', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('modified', 'datetime', [
                      'null' => true,
                      'update' => 'CURRENT_TIMESTAMP'
                    ])
        ->addIndex(['pattern'])
        ->create();

      $table= $this->table('product', [ 'signed' => false ]);
      $table
        ->addColumn('department_id', 'integer', [
                      'signed' => false,
                      'null' => true
                    ])
        ->addColumn('brand_id', 'integer', [
                      'signed' => false,
                      'null' => true
                    ])
        ->addColumn('name', 'string', [ 'limit' => 255 ])
        ->addColumn('description', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                    ])
        ->addColumn('slug', 'string', [ 'limit' => 120 ])
        ->addColumn('image', 'string', [ 'limit' => 255, 'default' => '' ])
        ->addColumn('variation_style', 'enum', [
                      'values' => [ 'tabs', 'flat' ],
                      'default' => 'flat',
                      'null' => true,
                    ])
        ->addColumn('added', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('modified', 'datetime', [
                      'null' => true,
                      'update' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('active', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 1,
                    ])
        ->addIndex(['department_id', 'brand_id', 'slug'], [ 'unique' => true ])
        ->addIndex(['name'])
        ->addIndex(['brand_id'])
        ->addIndex(['name', 'description'], [ 'type' => 'fulltext' ])
        ->create();

      $table= $this->table('product_to_image', [
                             'id' => false,
                             'primary_key' => ['product_id', 'image_id' ]
                           ]);
      $table
        ->addColumn('product_id', 'integer', [ 'signed' => false ])
        ->addColumn('image_id', 'integer', [ 'signed' => false ])
        ->create();

      $table= $this->table('prop65_warning', [ 'signed' => false ]);
      $table
        ->addColumn('warning', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                    ])
        ->create();

      $table= $this->table('redirect', [ 'signed' => false ]);
      $table
        ->addColumn('source', 'string', [ 'limit' => 512 ])
        ->addColumn('dest', 'string', [ 'limit' => 512 ])
        ->addColumn('added', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('modified', 'datetime', [
                      'null' => true,
                      'update' => 'CURRENT_TIMESTAMP'
                    ])
        ->addIndex(['source'])
        ->create();

      $table= $this->table('saved_search', [ 'signed' => false ]);
      $table
        ->addColumn('name', 'string', [ 'limit' => 255 ])
        ->addColumn('search', 'string', [ 'limit' => 255 ])
        ->addColumn('last_checked', 'date', [ 'null' => true ])
        ->create();

      $table= $this->table('timeclock', [ 'signed' => false ]);
      $table
        ->addColumn('person', 'integer', [ 'signed' => false ])
        ->addColumn('start', 'datetime')
        ->addColumn('end', 'datetime', [ 'null' => true ])
        ->create();

      $table= $this->table('timeclock_audit', [ 'signed' => false ]);
      $table
        ->addColumn('entry', 'integer', [ 'signed' => false, 'null' => true ])
        ->addColumn('before_start', 'datetime', [ 'null' => true ])
        ->addColumn('after_start', 'datetime', [ 'null' => true ])
        ->addColumn('before_end', 'datetime', [ 'null' => true ])
        ->addColumn('after_end', 'datetime', [ 'null' => true ])
        ->create();

      $table= $this->table('txn', [ 'signed' => false ]);
      $table
        ->addColumn('uuid', 'string', [ 'limit' => 50, 'null' => true ])
        ->addColumn('number', 'integer', [ 'signed' => false ])
        ->addColumn('created', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addColumn('filled', 'datetime', [ 'null' => true ])
        ->addColumn('paid', 'datetime', [ 'null' => true ])
        ->addColumn('type', 'enum', [
                      'values' => [
                        'correction', 'vendor', 'customer', 'drawer'
                      ],
                    ])
        ->addColumn('person', 'integer', [ 'signed' => false, 'null' => true ])
        ->addColumn('tax_rate', 'decimal', [
                      'precision' => 9,
                      'scale' => 3,
                    ])
        ->addColumn('returned_from', 'integer', [
                      'signed' => false,
                      'null' => true
                    ])
        ->addColumn('no_rewards', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addIndex(['type', 'number'], [ 'unique' => true ])
        ->addIndex(['uuid'], [ 'unique' => true ])
        ->addIndex(['created'])
        ->addIndex(['person'])
        ->create();

      $table= $this->table('txn_line', [ 'signed' => false ]);
      $table
        ->addColumn('txn', 'integer', [ 'signed' => false ])
        ->addColumn('line', 'integer', [ 'signed' => false, 'null' => true ])
        ->addColumn('item', 'integer', [ 'signed' => false, 'null' => true ])
        ->addColumn('ordered', 'integer')
        ->addColumn('allocated', 'integer', [ 'default' => 0 ])
        ->addColumn('override_name', 'string', [
                      'limit' => 255,
                      'null' => true
                    ])
        ->addColumn('data', 'blob', [
                      'limit' => MysqlAdapter::BLOB_MEDIUM,
                      'null' => true,
                    ])
        ->addColumn('retail_price', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                    ])
        ->addColumn('discount_type', 'enum', [
                      'values' => [
                        'percentage', 'relative', 'fixed'
                      ],
                      'null' => true
                    ])
        ->addColumn('discount', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true
                    ])
        ->addColumn('discount_manual', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addColumn('taxfree', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addColumn('tic', 'char', [
                      'limit' => 5,
                      'default' => '00000'
                    ])
        ->addColumn('tax', 'decimal', [
                      'precision' => 9,
                      'scale' => 3,
                      'default' => '0.000'
                    ])
        ->addIndex(['txn', 'line'])
        ->addIndex(['item'])
        ->create();

      $table= $this->table('txn_note', [ 'signed' => false ]);
      $table
        ->addColumn('txn', 'integer', [ 'signed' => false ])
        ->addColumn('entered', 'datetime')
        ->addColumn('content', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                    ])
        ->addColumn('public', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->create();

      $table= $this->table('vendor_item', [ 'signed' => false ]);
      $table
        ->addColumn('vendor', 'integer', [ 'signed' => false ])
        ->addColumn('item', 'integer', [ 'signed' => false, 'null' => true ])
        ->addColumn('code', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('vendor_sku', 'string', [ 'limit' => 255 ])
        ->addColumn('name', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('retail_price', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                    ])
        ->addColumn('net_price', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                    ])
        ->addColumn('promo_price', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true
                    ])
        ->addColumn('promo_quantity', 'integer', [ 'null' => true ])
        ->addColumn('barcode', 'string', [ 'limit' => 20, 'null' => true ])
        ->addColumn('purchase_quantity', 'integer')
        ->addColumn('length', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true
                    ])
        ->addColumn('width', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true
                    ])
        ->addColumn('height', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true
                    ])
        ->addColumn('weight', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true
                    ])
        ->addColumn('special_order', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'null' => true,
                      'default' => 0,
                    ])
        ->addColumn('prop65', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'null' => true
                    ])
        ->addColumn('hazmat', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'null' => true
                    ])
        ->addColumn('oversized', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'null' => true
                    ])
        ->addColumn('added', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('modified', 'datetime', [
                      'null' => true,
                      'update' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('active', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 1,
                    ])
        ->addIndex(['vendor','code'], [ 'unique' => true ])
        ->addIndex(['item'])
        ->addIndex(['vendor'])
        ->addIndex(['code'])
        ->addIndex(['vendor_sku'])
        ->create();

      $table= $this->table('wordform', [ 'signed' => false ]);
      $table
        ->addColumn('source', 'string', [ 'limit' => 512 ])
        ->addColumn('dest', 'string', [ 'limit' => 512 ])
        ->addColumn('added', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP'
                    ])
        ->addColumn('modified', 'datetime', [
                      'null' => true,
                      'update' => 'CURRENT_TIMESTAMP'
                    ])
        ->addIndex(['source'])
        ->create();
    }
}
