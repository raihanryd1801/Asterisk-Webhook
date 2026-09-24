<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debt_collectors', function (Blueprint $table) {
            // PIN login HP (ter-hash). Dibuat admin via collectors:pin.
            $table->string('pin', 255)->nullable()->after('api_token');
        });
    }

    public function down(): void
    {
        Schema::table('debt_collectors', function (Blueprint $table) {
            $table->dropColumn('pin');
        });
    }
};
