<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bape_orders', function (Blueprint $table) {
            // Agregamos la relaciÃ³n con la tabla clients (puede ser nulo si es cliente "PÃºblico")
            $table->foreignId('client_id')->nullable()->constrained('bape_clients')->nullOnDelete()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('bape_orders', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });
    }
};
