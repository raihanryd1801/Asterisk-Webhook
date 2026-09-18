<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Filter peran (src/dst + rentang tanggal) dipakai call history,
        // export, dan monitoring. Tanpa index komposit, orWhere memaksa
        // full scan di tabel jutaan baris.
        Schema::table('cdr_live', function (Blueprint $table) {
            $table->index(['src', 'calldate'], 'cdr_live_src_calldate_index');
            $table->index(['dst', 'calldate'], 'cdr_live_dst_calldate_index');
        });
    }

    public function down(): void
    {
        Schema::table('cdr_live', function (Blueprint $table) {
            $table->dropIndex('cdr_live_src_calldate_index');
            $table->dropIndex('cdr_live_dst_calldate_index');
        });
    }
};
