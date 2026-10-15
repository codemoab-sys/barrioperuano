<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->hardenConsumptionIdentity();
        $this->hardenDeletionAudit();

        if ($this->serverEnforcesCheckConstraints()) {
            $this->addCheckConstraints();
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        if ($this->serverEnforcesCheckConstraints()) {
            foreach ($this->checkConstraints() as [$table, $name]) {
                DB::statement("ALTER TABLE `{$table}` DROP CHECK `{$name}`");
            }
        }

        DB::statement('ALTER TABLE `bape_kitchen_inventory_movements` DROP FOREIGN KEY `fk_kitchen_movements_operation`');
        DB::statement('ALTER TABLE `bape_kitchen_inventory_movements` ADD CONSTRAINT `bape_kitchen_inventory_movements_operation_id_foreign` FOREIGN KEY (`operation_id`) REFERENCES `bape_kitchen_inventory_operations` (`id`) ON DELETE SET NULL');

        DB::statement('ALTER TABLE `bape_kitchen_order_consumptions` DROP FOREIGN KEY `fk_kitchen_consumptions_operation`');
        DB::statement('ALTER TABLE `bape_kitchen_order_consumptions` ADD CONSTRAINT `fk_kitchen_consumptions_operation` FOREIGN KEY (`operation_id`) REFERENCES `bape_kitchen_inventory_operations` (`id`) ON DELETE CASCADE');

        DB::statement('ALTER TABLE `bape_kitchen_shortage_regularizations` DROP FOREIGN KEY `fk_kitchen_regularizations_operation`');
        DB::statement('ALTER TABLE `bape_kitchen_shortage_regularizations` ADD CONSTRAINT `fk_kitchen_regularizations_operation` FOREIGN KEY (`operation_id`) REFERENCES `bape_kitchen_inventory_operations` (`id`) ON DELETE CASCADE');

        DB::statement('ALTER TABLE `bape_kitchen_shortage_regularizations` DROP FOREIGN KEY `fk_kitchen_regularizations_consumption`');
        DB::statement('ALTER TABLE `bape_kitchen_shortage_regularizations` ADD CONSTRAINT `fk_kitchen_regularizations_consumption` FOREIGN KEY (`kitchen_order_consumption_id`) REFERENCES `bape_kitchen_order_consumptions` (`id`) ON DELETE CASCADE');

        DB::statement('ALTER TABLE `bape_kitchen_order_consumptions` ADD UNIQUE `uniq_kitchen_consumptions_op_supply_detail` (`operation_id`, `kitchen_supply_id`, `order_detail_id`)');
        DB::statement('ALTER TABLE `bape_kitchen_order_consumptions` DROP INDEX `uniq_kitchen_consumptions_operation_idempotency`');
    }

    private function hardenConsumptionIdentity(): void
    {
        DB::statement('ALTER TABLE `bape_kitchen_order_consumptions` ADD UNIQUE `uniq_kitchen_consumptions_operation_idempotency` (`operation_id`, `idempotency_key`)');
        DB::statement('ALTER TABLE `bape_kitchen_order_consumptions` DROP INDEX `uniq_kitchen_consumptions_op_supply_detail`');
    }

    private function hardenDeletionAudit(): void
    {
        DB::statement('ALTER TABLE `bape_kitchen_inventory_movements` DROP FOREIGN KEY `bape_kitchen_inventory_movements_operation_id_foreign`');
        DB::statement('ALTER TABLE `bape_kitchen_inventory_movements` ADD CONSTRAINT `fk_kitchen_movements_operation` FOREIGN KEY (`operation_id`) REFERENCES `bape_kitchen_inventory_operations` (`id`) ON DELETE RESTRICT');

        DB::statement('ALTER TABLE `bape_kitchen_order_consumptions` DROP FOREIGN KEY `fk_kitchen_consumptions_operation`');
        DB::statement('ALTER TABLE `bape_kitchen_order_consumptions` ADD CONSTRAINT `fk_kitchen_consumptions_operation` FOREIGN KEY (`operation_id`) REFERENCES `bape_kitchen_inventory_operations` (`id`) ON DELETE RESTRICT');

        DB::statement('ALTER TABLE `bape_kitchen_shortage_regularizations` DROP FOREIGN KEY `fk_kitchen_regularizations_operation`');
        DB::statement('ALTER TABLE `bape_kitchen_shortage_regularizations` ADD CONSTRAINT `fk_kitchen_regularizations_operation` FOREIGN KEY (`operation_id`) REFERENCES `bape_kitchen_inventory_operations` (`id`) ON DELETE RESTRICT');

        DB::statement('ALTER TABLE `bape_kitchen_shortage_regularizations` DROP FOREIGN KEY `fk_kitchen_regularizations_consumption`');
        DB::statement('ALTER TABLE `bape_kitchen_shortage_regularizations` ADD CONSTRAINT `fk_kitchen_regularizations_consumption` FOREIGN KEY (`kitchen_order_consumption_id`) REFERENCES `bape_kitchen_order_consumptions` (`id`) ON DELETE RESTRICT');
    }

    private function addCheckConstraints(): void
    {
        foreach ($this->checkConstraints() as [$table, $name, $definition]) {
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$definition})");
        }
    }

    private function checkConstraints(): array
    {
        return [
            ['bape_kitchen_supplies', 'chk_kitchen_supplies_stock_non_negative', 'stock_current >= 0'],
            ['bape_kitchen_recipe_items', 'chk_kitchen_recipe_items_quantity_positive', 'quantity > 0'],
            ['bape_kitchen_inventory_movements', 'chk_kitchen_movements_quantities_non_negative', 'quantity >= 0 AND covered_quantity >= 0 AND shortage_quantity >= 0 AND (theoretical_quantity IS NULL OR theoretical_quantity >= 0)'],
            ['bape_kitchen_inventory_movements', 'chk_kitchen_movements_stock_non_negative', 'stock_before >= 0 AND stock_after >= 0'],
            ['bape_kitchen_order_consumptions', 'chk_kitchen_consumptions_quantities_non_negative', 'sold_quantity >= 0 AND recipe_quantity >= 0 AND theoretical_quantity >= 0 AND consumed_quantity >= 0 AND covered_quantity >= 0 AND shortage_quantity >= 0 AND returned_quantity >= 0'],
            ['bape_kitchen_order_consumptions', 'chk_kitchen_consumptions_returned_within_covered', 'returned_quantity <= covered_quantity'],
            ['bape_kitchen_order_consumptions', 'chk_kitchen_consumptions_covered_shortage_coherent', 'consumed_quantity + 0.0001 >= covered_quantity + shortage_quantity AND covered_quantity + shortage_quantity + 0.0001 >= consumed_quantity'],
            ['bape_kitchen_shortage_regularizations', 'chk_kitchen_regularizations_quantity_positive', 'quantity > 0'],
        ];
    }

    private function serverEnforcesCheckConstraints(): bool
    {
        $version = (string) (DB::selectOne('SELECT VERSION() AS version')->version ?? '');

        if (! preg_match('/(\d+)\.(\d+)(?:\.(\d+))?/', $version, $matches)) {
            return false;
        }

        $major = (int) $matches[1];
        $minor = (int) $matches[2];
        $patch = (int) ($matches[3] ?? 0);
        $isMaria = Schema::getConnection()->getDriverName() === 'mariadb' || stripos($version, 'mariadb') !== false;

        if ($isMaria) {
            return $major > 10 || ($major === 10 && $minor >= 2);
        }

        return $major > 8 || ($major === 8 && ($minor > 0 || $patch >= 16));
    }
};
