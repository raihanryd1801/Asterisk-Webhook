<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Peta LID (ID samaran WA baru) -> nomor telepon asli, per sesi.
        // Diisi otomatis setiap pesan masuk yang berhasil dipetakan;
        // dipakai agar kiriman dari HP (fromMe via @lid) gabung ke thread
        // yang sama, bukan jadi percakapan baru.
        Schema::create('wa_lid_map', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64);
            $table->string('lid', 30);
            $table->string('phone', 20);
            $table->timestamps();

            $table->unique(['session_id', 'lid']);
            $table->index(['session_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_lid_map');
    }
};
