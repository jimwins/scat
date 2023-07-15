<?php


use Phinx\Seed\AbstractSeed;

class DemoSeeder extends AbstractSeed
{
  public function shouldExecute() {
    $row= $this->fetchRow("SELECT value FROM config WHERE name = 'tax.default_rate'");
    /* Should not execute if tax.default_rate is set */
    return is_array($row) && !array_key_exists('value', $row);
  }

  public function run()
  {
    /* In case we're running an older Phinx without shouldExecute() */
    $row= $this->fetchRow("SELECT value FROM config WHERE name = 'tax.default_rate'");
    if (is_array($row) && array_key_exists('value', $row)) {
      return;
    }

    /* Default config: a tax rate */
    $config= $this->table('config');
    $config->insert([
      [ 'name' => 'tax.default_rate', 'value' => '7.5' ],
    ])->saveData();

    $items= $this->table('item');
    $items->insert([
      [ 'code' => 'ZZ-GIFTCARD', 'name' => 'Gift Card', 'tic' => '10005', 'tax_free' => 1 ],
      [ 'code' => 'ACME-001', 'name' => 'Anvil', 'retail_price' => 100.00 ],
      [ 'code' => 'ACME-002', 'name' => 'Toaster', 'retail_price' => 49.99 ],
      [ 'code' => 'ACME-003', 'name' => 'Super Outfit', 'retail_price' => 24.99 ],
      [ 'code' => 'ACME-004', 'name' => 'Aspirin', 'retail_price' => 2.99 ],
      [ 'code' => 'ACME-005', 'name' => 'Matches', 'retail_price' => 1.99 ],
      [ 'code' => 'ACME-006', 'name' => 'Rocket-Powered Roller Skates', 'retail_price' => 19.99 ],
      [ 'code' => 'ACME-007', 'name' => 'Bird Seed', 'retail_price' => 4.99 ],
    ])->saveData();
  }
}
