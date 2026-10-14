<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const BARCO_GRANDE = 'Barco Grande (70 alitas, papas fritas y arroz chaufa, cualquier sabor)';

    public function up(): void
    {
        DB::table('bape_products')
            ->where('name', self::BARCO_GRANDE)
            ->update(['required_sauce_count' => 10]);
    }

    public function down(): void
    {
        DB::table('bape_products')
            ->where('name', self::BARCO_GRANDE)
            ->update(['required_sauce_count' => 1]);
    }
};
