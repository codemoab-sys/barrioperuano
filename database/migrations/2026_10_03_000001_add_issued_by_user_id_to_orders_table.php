<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bape_orders', function (Blueprint $table) {
            $table->foreignId('issued_by_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('bape_users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bape_orders', function (Blueprint $table) {
            $table->dropForeign(['issued_by_user_id']);
            $table->dropColumn('issued_by_user_id');
        });
    }
};