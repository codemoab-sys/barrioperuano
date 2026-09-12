<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bape_document_series', function (Blueprint $table) {
            $table->id();
            // CÃ³digos SUNAT: 01 Factura, 03 Boleta, 07 Nota CrÃ©dito, 08 Nota DÃ©bito
            $table->string('document_code', 2);                     // 01 / 03 / 07 / 08
            $table->string('document_type', 30);                    // factura / boleta / nota_credito / nota_debito
            $table->string('serie', 4)->unique();                   // B001 / F001 / FC01 / BC01
            $table->unsignedInteger('last_number')->default(0);     // Ãšltimo correlativo emitido
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bape_document_series');
    }
};
