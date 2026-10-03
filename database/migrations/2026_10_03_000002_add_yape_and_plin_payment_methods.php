<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bape_deliveries', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'yape', 'plin'])
                ->default('cash')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('bape_deliveries', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'card', 'transfer'])
                ->default('cash')
                ->change();
        });
    }
};