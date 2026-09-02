<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cdr_live', function (Blueprint $table) {
            // 1. Tambah kolom notes jika belum ada (mencegah error Unknown column 'notes')
            if (!Schema::hasColumn('cdr_live', 'notes')) {
                $table->text('notes')->nullable()->after('terminated_by');
            }

            // 2. Perlebar kolom sip_code jadi VARCHAR(50) (mencegah Data too long)
            if (Schema::hasColumn('cdr_live', 'sip_code')) {
                $table->string('sip_code', 50)->nullable()->change();
            } else {
                $table->string('sip_code', 50)->nullable();
            }

            // 3. Perlebar kolom terminated_by jadi VARCHAR(50) untuk menampung label/nomor
            if (Schema::hasColumn('cdr_live', 'terminated_by')) {
                $table->string('terminated_by', 50)->nullable()->change();
            } else {
                $table->string('terminated_by', 50)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cdr_live', function (Blueprint $table) {
            // Rollback jika diperlukan
            $table->dropColumn(['notes']);
            // Untuk sip_code & terminated_by bisa dikembalikan ke type asal jika perlu
        });
    }
};