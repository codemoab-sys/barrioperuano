<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bape_orders', function (Blueprint $table) {
            $table->foreignId('cash_register_id')->nullable()->constrained('bape_cash_registers')->onDelete('set null');
        });

        Schema::table('bape_expenses', function (Blueprint $table) {
            $table->foreignId('cash_register_id')->nullable()->constrained('bape_cash_registers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bape_orders', function (Blueprint $table) {
            $table->dropForeign(['cash_register_id']);
            $table->dropColumn('cash_register_id');
        });

        Schema::table('bape_expenses', function (Blueprint $table) {
            $table->dropForeign(['cash_register_id']);
            $table->dropColumn('cash_register_id');
        });
    }
};
