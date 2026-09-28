<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bape_order_details', function (Blueprint $table) {
            $table->json('sauce_choices')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('bape_order_details', function (Blueprint $table) {
            $table->dropColumn('sauce_choices');
        });
    }
};