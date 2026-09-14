<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dial_queue_items', function (Blueprint $table) {
            // PDS customer-first: vonis AMD saat customer angkat (HUMAN/MACHINE/NOTSURE)
            $table->string('amd_verdict', 20)->nullable()->after('agent_extension');
            // Ext agent yang akhirnya disambung (bisa beda dari reservasi awal)
            $table->string('bridged_agent', 20)->nullable()->after('amd_verdict');
            // Waktu customer mengangkat (diisi endpoint bridge AGI)
            $table->timestamp('answered_at')->nullable()->after('last_attempt_at');
        });
    }

    public function down(): void
    {
        Schema::table('dial_queue_items', function (Blueprint $table) {
            $table->dropColumn(['amd_verdict', 'bridged_agent', 'answered_at']);
        });
    }
};
