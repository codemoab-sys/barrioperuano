<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $categoryId = DB::table('bape_categories')
            ->where('name', 'Servicio')
            ->value('id');

        if (!$categoryId) {
            $categoryId = DB::table('bape_categories')->insertGetId([
                'name' => 'Servicio',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $productExists = DB::table('bape_products')
            ->where('category_id', $categoryId)
            ->where('name', 'Servicio Delivery')
            ->exists();

        if (!$productExists) {
            DB::table('bape_products')->insert([
                'category_id' => $categoryId,
                'name' => 'Servicio Delivery',
                'price' => 0,
                'cost' => 0,
                'stock' => null,
                'is_active' => true,
                'is_saleable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $categoryId = DB::table('bape_categories')
            ->where('name', 'Servicio')
            ->value('id');

        if (!$categoryId) {
            return;
        }

        DB::table('bape_products')
            ->where('category_id', $categoryId)
            ->where('name', 'Servicio Delivery')
            ->delete();

        if (!DB::table('bape_products')->where('category_id', $categoryId)->exists()) {
            DB::table('bape_categories')->where('id', $categoryId)->delete();
        }
    }
};
