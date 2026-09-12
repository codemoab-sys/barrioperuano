<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bape_users MODIFY role ENUM('admin', 'cashier', 'waiter', 'kitchen', 'cashier_waiter') NOT NULL DEFAULT 'admin'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE bape_users SET role = 'cashier' WHERE role = 'cashier_waiter'");
            DB::statement("ALTER TABLE bape_users MODIFY role ENUM('admin', 'cashier', 'waiter', 'kitchen') NOT NULL DEFAULT 'admin'");
        }
    }
};