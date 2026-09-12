<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bape_orders', function (Blueprint $table) {
            $table->string('anulacion_status', 30)->nullable()->after('sent_at');
            $table->string('anulacion_ticket', 30)->nullable()->after('anulacion_status');
            $table->text('anulacion_description')->nullable()->after('anulacion_ticket');
            $table->string('anulacion_pdf_path')->nullable()->after('anulacion_description');
            $table->string('anulacion_xml_path')->nullable()->after('anulacion_pdf_path');
            $table->string('anulacion_cdr_path')->nullable()->after('anulacion_xml_path');
            $table->timestamp('anulacion_sent_at')->nullable()->after('anulacion_cdr_path');
        });
    }

    public function down(): void
    {
        Schema::table('bape_orders', function (Blueprint $table) {
            $table->dropColumn([
                'anulacion_status', 'anulacion_ticket', 'anulacion_description',
                'anulacion_pdf_path', 'anulacion_xml_path', 'anulacion_cdr_path',
                'anulacion_sent_at',
            ]);
        });
    }
};