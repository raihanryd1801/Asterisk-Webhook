<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dial_jobs', function (Blueprint $table) {
            // Loop:ON = antrean habis dibangun ulang otomatis (attempt di-reset),
            // job tidak pernah completed kecuali SPV tekan Stop.
            $table->boolean('loop')->default(false)->after('max_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('dial_jobs', function (Blueprint $table) {
            $table->dropColumn('loop');
        });
    }
};
