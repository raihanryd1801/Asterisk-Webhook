<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debt_collectors', function (Blueprint $table) {
            // Token otentikasi HP collector (header Authorization: Bearer).
            // Dibuat via: php artisan collectors:token {id}
            $table->string('api_token', 64)->nullable()->unique()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('debt_collectors', function (Blueprint $table) {
            $table->dropColumn('api_token');
        });
    }
};
