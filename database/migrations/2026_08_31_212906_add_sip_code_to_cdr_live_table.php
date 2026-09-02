<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cdr_live', function (Blueprint $table) {
            $table->string('sip_code', 10)->nullable()->after('cnum');
        });
    }

    public function down(): void
    {
        Schema::table('cdr_live', function (Blueprint $table) {
            $table->dropColumn('sip_code');
        });
    }
};