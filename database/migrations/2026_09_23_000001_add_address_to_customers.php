<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Alamat tagihan/domisiil + tombol buka Google Maps (tanpa API key,
            // pakai universal link maps/search). Dipakai collector lapangan.
            $table->text('address')->nullable()->after('company');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }
};
