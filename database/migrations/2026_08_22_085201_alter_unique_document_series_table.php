<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bape_document_series', function (Blueprint $table) {
            $table->dropUnique('bape_document_series_serie_unique');
            $table->unique(['serie', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::table('bape_document_series', function (Blueprint $table) {
            $table->dropUnique(['serie', 'document_type']);
            $table->unique('serie');
        });
    }
};
