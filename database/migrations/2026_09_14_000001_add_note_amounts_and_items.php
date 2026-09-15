<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bape_credit_notes', function (Blueprint $table) {
            $table->json('items')->nullable()->after('reason_description');
        });

        Schema::table('bape_debit_notes', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable()->after('reason_description');
        });
    }

    public function down(): void
    {
        Schema::table('bape_credit_notes', function (Blueprint $table) {
            $table->dropColumn('items');
        });

        Schema::table('bape_debit_notes', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
};