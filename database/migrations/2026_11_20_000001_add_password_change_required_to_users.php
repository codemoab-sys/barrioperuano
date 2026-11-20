<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bape_users', 'password_change_required')) {
            Schema::table('bape_users', function (Blueprint $table) {
                $table->boolean('password_change_required')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bape_users', 'password_change_required')) {
            Schema::table('bape_users', function (Blueprint $table) {
                $table->dropColumn('password_change_required');
            });
        }
    }
};
