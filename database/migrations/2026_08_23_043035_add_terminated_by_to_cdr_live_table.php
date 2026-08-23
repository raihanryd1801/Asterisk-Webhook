<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
       public function up(): void
    {
        Schema::table('cdr_live', function (Blueprint $table) {
            // Isinya: "Agent" kalau agent yang menutup duluan, atau nomor tujuan
            // (mis. "087786868966") kalau pihak tujuan/trunk yang menutup duluan.
            // Nullable karena tidak semua row (mis. yang cuma dari cdr:sync tanpa
            // pernah lewat ami:listen) akan punya info ini.
            $table->string('terminated_by', 40)->nullable()->after('sip_code');
        });
    }
 
    public function down(): void
    {
        Schema::table('cdr_live', function (Blueprint $table) {
            $table->dropColumn('terminated_by');
        });
    }
};
