<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bape_orders', function (Blueprint $table) {
            // NumeraciÃ³n SUNAT
            $table->string('serie', 4)->nullable()->after('document_type');             // B001 / F001
            $table->unsignedInteger('correlativo')->nullable()->after('serie');         // 1, 2, 3 ...

            // Importes desglosados (PerÃº: IGV 18%)
            $table->decimal('subtotal', 12, 2)->default(0)->after('total');             // Base gravada
            $table->decimal('igv', 12, 2)->default(0)->after('subtotal');               // 18% del subtotal
            $table->decimal('total_gravada', 12, 2)->default(0)->after('igv');          // Op. gravadas
            $table->decimal('total_exonerada', 12, 2)->default(0)->after('total_gravada');
            $table->decimal('total_inafecta', 12, 2)->default(0)->after('total_exonerada');
            $table->decimal('total_gratuita', 12, 2)->default(0)->after('total_inafecta');

            // Trazabilidad SUNAT
            $table->string('sunat_status', 30)->default('PENDING')->after('total_gratuita');
            // PENDING | SENT | ACCEPTED | OBSERVED | REJECTED | ERROR | VOIDED
            $table->string('sunat_code', 10)->nullable()->after('sunat_status');
            $table->text('sunat_description')->nullable()->after('sunat_code');
            $table->string('xml_path')->nullable()->after('sunat_description');
            $table->string('cdr_path')->nullable()->after('xml_path');
            $table->string('pdf_path')->nullable()->after('cdr_path');
            $table->string('hash', 100)->nullable()->after('pdf_path');                 // Resumen / Digest del XML
            $table->timestamp('sent_at')->nullable()->after('hash');

            $table->unique(['document_type', 'serie', 'correlativo'], 'orders_doc_serie_corr_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bape_orders', function (Blueprint $table) {
            $table->dropUnique('orders_doc_serie_corr_unique');
            $table->dropColumn([
                'serie', 'correlativo',
                'subtotal', 'igv',
                'total_gravada', 'total_exonerada', 'total_inafecta', 'total_gratuita',
                'sunat_status', 'sunat_code', 'sunat_description',
                'xml_path', 'cdr_path', 'pdf_path',
                'hash', 'sent_at',
            ]);
        });
    }
};
