<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Koreksi: nomor utama = kolom phone (wajib & unik).
        // personal_phone redundan — cukup 3 nomor: phone, office_phone, emergency_phone.
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('personal_phone');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('personal_phone', 30)->nullable()->after('phone');
        });
    }
};
