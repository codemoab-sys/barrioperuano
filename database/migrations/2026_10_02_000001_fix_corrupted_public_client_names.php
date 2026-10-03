<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bape_orders')
            ->where('client_name', 'PÃºblico General')
            ->update(['client_name' => 'Público General']);
    }

    public function down(): void
    {
        // The data correction is intentionally not reverted.
    }
};
