<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bape_order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('bape_orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('bape_products');
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2);
            $table->enum('status', ['pending', 'cooking', 'served'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bape_order_details');
    }
};