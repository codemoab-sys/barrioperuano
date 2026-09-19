<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bape_users', function (Blueprint $table) {
            $table->boolean('all_areas')->default(true)->after('role');
        });

        Schema::create('bape_area_user', function (Blueprint $table) {
            $table->foreignId('area_id')->constrained('bape_areas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('bape_users')->cascadeOnDelete();
            $table->primary(['area_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bape_area_user');
        Schema::table('bape_users', function (Blueprint $table) {
            $table->dropColumn('all_areas');
        });
    }
};