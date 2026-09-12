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
        Schema::table('bape_products', function (Blueprint $table) {
            $table->decimal('promotional_price', 10, 2)->nullable()->after('price');
            $table->boolean('is_chef_recommendation')->default(false)->after('is_active');
            $table->boolean('is_new')->default(false)->after('is_chef_recommendation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bape_products', function (Blueprint $table) {
            $table->dropColumn(['promotional_price', 'is_chef_recommendation', 'is_new']);
        });
    }
};
