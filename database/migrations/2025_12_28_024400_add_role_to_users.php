<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SOLO crea la columna si NO existe
        if (!Schema::hasColumn('bape_users', 'role')) {
            Schema::table('bape_users', function (Blueprint $table) {
                $table->string('role')->default('waiter')->after('email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bape_users', 'role')) {
            Schema::table('bape_users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};