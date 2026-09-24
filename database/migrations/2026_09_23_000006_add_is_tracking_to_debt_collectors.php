<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debt_collectors', function (Blueprint $table) {
            // HP collector menekan "hentikan tracking" -> false (offline seketika).
            // Otomatis true lagi saat posisi baru masuk (tracking dimulai).
            $table->boolean('is_tracking')->default(true)->after('api_token');
        });
    }

    public function down(): void
    {
        Schema::table('debt_collectors', function (Blueprint $table) {
            $table->dropColumn('is_tracking');
        });
    }
};
