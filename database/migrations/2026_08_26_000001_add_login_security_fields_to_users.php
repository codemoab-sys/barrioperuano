<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bape_users', function (Blueprint $table) {
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bape_users', function (Blueprint $table) {
            $table->dropColumn(['failed_login_attempts', 'locked_at']);
        });
    }
};
