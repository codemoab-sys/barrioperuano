<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bape_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('bape_orders')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('bape_clients')->nullOnDelete();
            $table->string('client_name');
            $table->string('client_phone');
            $table->text('address');
            $table->string('reference')->nullable();
            $table->foreignId('driver_id')->nullable()->constrained('bape_delivery_drivers')->nullOnDelete();
            $table->foreignId('user_id')->constrained('bape_users');
            $table->foreignId('cash_register_id')->nullable()->constrained('bape_cash_registers')->nullOnDelete();
            $table->enum('status', ['pending', 'preparing', 'on_way', 'delivered', 'cancelled'])->default('pending');
            $table->enum('payment_method', ['cash', 'card', 'transfer'])->default('cash');
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bape_deliveries');
    }
};
