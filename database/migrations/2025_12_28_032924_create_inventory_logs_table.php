<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bape_inventory_logs', function (Blueprint $table) {
            $table->id();
            // Estas son las columnas que faltaban en tu archivo:
            $table->foreignId('product_id')->constrained('bape_products')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('bape_users');
            $table->string('type'); // 'sale', 'purchase', 'adjustment'
            $table->integer('quantity');
            $table->integer('old_stock')->nullable();
            $table->integer('new_stock')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bape_inventory_logs');
    }
};
