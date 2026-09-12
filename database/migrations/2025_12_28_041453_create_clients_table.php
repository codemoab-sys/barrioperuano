<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bape_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nombre o RazÃ³n Social
            $table->string('document_type')->default('DNI'); // DNI, RUC
            $table->string('document_number')->nullable(); // NÃºmero
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bape_clients');
    }
};