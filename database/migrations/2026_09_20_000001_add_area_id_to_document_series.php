<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bape_document_series', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('id')->constrained('bape_areas')->nullOnDelete();
        });

        $firstAreaId = DB::table('bape_areas')->orderBy('id')->value('id');
        if ($firstAreaId) {
            DB::table('bape_document_series')->whereNull('area_id')->update(['area_id' => $firstAreaId]);
        }

        Schema::table('bape_document_series', function (Blueprint $table) {
            $table->unique(['area_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::table('bape_document_series', function (Blueprint $table) {
            $table->dropUnique('bape_document_series_area_id_document_type_unique');
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
        });
    }
};