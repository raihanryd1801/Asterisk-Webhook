<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Label lokasi hasil geocode (display_name Nominatim) — jejak audit
            // agar titik nyasar (beda kota) bisa dilacak & dikoreksi.
            $table->text('geocode_label')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('geocode_label');
        });
    }
};
