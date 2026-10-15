<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bape_kitchen_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bape_kitchen_supplies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('bape_kitchen_categories')->restrictOnDelete();
            $table->string('name');
            $table->string('unit', 30);
            $table->decimal('stock_current', 14, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['category_id', 'name']);
        });

        Schema::create('bape_kitchen_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('bape_products')->restrictOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('product_id');
        });

        Schema::create('bape_kitchen_recipe_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('bape_kitchen_recipes')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['recipe_id', 'version']);
        });

        Schema::create('bape_kitchen_recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_version_id')->constrained('bape_kitchen_recipe_versions')->cascadeOnDelete();
            $table->foreignId('kitchen_supply_id')->constrained('bape_kitchen_supplies')->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->timestamps();
            $table->unique(['recipe_version_id', 'kitchen_supply_id'], 'uniq_kitchen_recipe_items_version_supply');
        });

        Schema::create('bape_kitchen_inventory_operations', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 191)->unique();
            $table->string('operation_type', 40);
            $table->char('request_hash', 64);
            $table->string('status', 20)->default('confirmed');
            $table->foreignId('user_id')->nullable()->constrained('bape_users')->nullOnDelete();
            $table->timestamps();
            $table->index(['operation_type', 'created_at'], 'idx_kitchen_operations_type_created');
        });

        Schema::create('bape_kitchen_inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_id')->nullable()->constrained('bape_kitchen_inventory_operations')->nullOnDelete();
            $table->foreignId('kitchen_supply_id')->constrained('bape_kitchen_supplies')->restrictOnDelete();
            $table->string('type', 30);
            $table->decimal('theoretical_quantity', 14, 4)->nullable();
            $table->decimal('quantity', 14, 4);
            $table->decimal('covered_quantity', 14, 4)->default(0);
            $table->decimal('shortage_quantity', 14, 4)->default(0);
            $table->decimal('stock_before', 14, 4);
            $table->decimal('stock_after', 14, 4);
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->foreignId('user_id')->nullable()->constrained('bape_users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['kitchen_supply_id', 'created_at'], 'idx_kitchen_movements_supply_created');
            $table->index(['source_type', 'source_id']);
            $table->index('type');
        });

        Schema::create('bape_kitchen_order_consumptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operation_id');
            $table->foreign('operation_id', 'fk_kitchen_consumptions_operation')->references('id')->on('bape_kitchen_inventory_operations')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('bape_orders')->nullOnDelete();
            $table->foreignId('order_detail_id')->nullable()->constrained('bape_order_details')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('bape_products')->nullOnDelete();
            $table->foreignId('recipe_version_id')->constrained('bape_kitchen_recipe_versions')->restrictOnDelete();
            $table->foreignId('kitchen_supply_id')->constrained('bape_kitchen_supplies')->restrictOnDelete();
            $table->decimal('sold_quantity', 14, 4);
            $table->decimal('recipe_quantity', 14, 4);
            $table->decimal('theoretical_quantity', 14, 4);
            $table->decimal('consumed_quantity', 14, 4);
            $table->decimal('covered_quantity', 14, 4);
            $table->decimal('shortage_quantity', 14, 4);
            $table->decimal('returned_quantity', 14, 4)->default(0);
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();
            $table->unique(['operation_id', 'kitchen_supply_id', 'order_detail_id'], 'uniq_kitchen_consumptions_op_supply_detail');
            $table->index(['order_id', 'order_detail_id']);
            $table->index(['kitchen_supply_id', 'created_at'], 'idx_kitchen_consumptions_supply_created');
        });

        Schema::create('bape_kitchen_shortage_regularizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operation_id');
            $table->foreign('operation_id', 'fk_kitchen_regularizations_operation')->references('id')->on('bape_kitchen_inventory_operations')->cascadeOnDelete();
            $table->unsignedBigInteger('kitchen_order_consumption_id');
            $table->foreign('kitchen_order_consumption_id', 'fk_kitchen_regularizations_consumption')->references('id')->on('bape_kitchen_order_consumptions')->cascadeOnDelete();
            $table->foreignId('kitchen_supply_id')->constrained('bape_kitchen_supplies')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('bape_users')->nullOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->string('regularization_key', 191);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['regularization_key', 'kitchen_order_consumption_id'], 'uniq_kitchen_regularizations_key_consumption');
            $table->index(['kitchen_supply_id', 'created_at'], 'idx_kitchen_regularizations_supply_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bape_kitchen_shortage_regularizations');
        Schema::dropIfExists('bape_kitchen_order_consumptions');
        Schema::dropIfExists('bape_kitchen_inventory_movements');
        Schema::dropIfExists('bape_kitchen_inventory_operations');
        Schema::dropIfExists('bape_kitchen_recipe_items');
        Schema::dropIfExists('bape_kitchen_recipe_versions');
        Schema::dropIfExists('bape_kitchen_recipes');
        Schema::dropIfExists('bape_kitchen_supplies');
        Schema::dropIfExists('bape_kitchen_categories');
    }
};
