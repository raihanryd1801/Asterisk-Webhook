<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blast_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('blast_logs', 'sender')) {
                $table->string('sender')->nullable()->after('bucket')->comment('Sesi pengirim, mis. user:3 / spv:102 + nomor WA');
            }
        });
    }

    public function down(): void
    {
        Schema::table('blast_logs', function (Blueprint $table) {
            if (Schema::hasColumn('blast_logs', 'sender')) {
                $table->dropColumn('sender');
            }
        });
    }
};