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
        Schema::table('bape_tables', function (Blueprint $table) {
            // Verificamos si la columna NO existe antes de crearla para evitar errores
            if (!Schema::hasColumn('bape_tables', 'x_pos')) {
                $table->integer('x_pos')->default(10)->after('status');
                $table->integer('y_pos')->default(10)->after('x_pos');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bape_tables', function (Blueprint $table) {
            if (Schema::hasColumn('bape_tables', 'x_pos')) {
                $table->dropColumn(['x_pos', 'y_pos']);
            }
        });
    }
};