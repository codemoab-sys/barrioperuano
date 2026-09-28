<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BARCO_GRANDE = 'Barco Grande (70 alitas, papas fritas y arroz chaufa, cualquier sabor)';

    public function up(): void
    {
        Schema::table('bape_products', function (Blueprint $table) {
            $table->unsignedTinyInteger('required_sauce_count')->nullable()->after('is_saleable');
        });

        DB::transaction(function (): void {
            $sauceCounts = [
                'El Al Toque (6 alas, 1 sabor)' => 1,
                'El Monse (10 alas, 2 sabores)' => 2,
                'El Firme (20 alas, 4 sabores)' => 4,
                'El Bravazo (30 alas, 5 sabores)' => 5,
                'El Respetado (40 alas, 8 sabores)' => 8,
                'El Padrino (50 alas, 10 sabores)' => 10,
                'El Intocable (70 alas, 10 sabores)' => 10,
                self::BARCO_GRANDE => 1,
            ];

            foreach ($sauceCounts as $name => $count) {
                DB::table('bape_products')->where('name', $name)->update(['required_sauce_count' => $count]);
            }

            $alitasCategoryId = DB::table('bape_categories')->where('name', 'Alitas')->value('id');
            $barcosCategoryId = DB::table('bape_categories')->where('name', 'Barcos')->value('id');

            if ($alitasCategoryId && $barcosCategoryId) {
                DB::table('bape_products')
                    ->where('category_id', $alitasCategoryId)
                    ->where('name', self::BARCO_GRANDE)
                    ->update(['category_id' => $barcosCategoryId]);
            }
        });
    }

    public function down(): void
    {
        $alitasCategoryId = DB::table('bape_categories')->where('name', 'Alitas')->value('id');
        $barcosCategoryId = DB::table('bape_categories')->where('name', 'Barcos')->value('id');

        if ($alitasCategoryId && $barcosCategoryId) {
            DB::table('bape_products')
                ->where('category_id', $barcosCategoryId)
                ->where('name', self::BARCO_GRANDE)
                ->update(['category_id' => $alitasCategoryId]);
        }

        Schema::table('bape_products', function (Blueprint $table) {
            $table->dropColumn('required_sauce_count');
        });
    }
};