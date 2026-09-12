<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bape_debit_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('bape_orders')->cascadeOnDelete();

            $table->string('serie', 4);
            $table->unsignedInteger('correlativo');
            $table->string('document_type', 30)->default('nota_debito');

            // Catálogo 10 SUNAT
            // 1 Intereses por mora
            // 2 Aumento de valor
            // 3 Penalidades
            // 4 Ajustes afectos al IVAP
            // 5 Ajustes de operaciones de exportación
            $table->string('reason_code', 2);
            $table->string('reason_description');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('sunat_status', 30)->default('PENDING');
            $table->string('sunat_code', 10)->nullable();
            $table->text('sunat_description')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('hash', 100)->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('bape_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['serie', 'correlativo'], 'debit_notes_serie_corr_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bape_debit_notes');
    }
};
