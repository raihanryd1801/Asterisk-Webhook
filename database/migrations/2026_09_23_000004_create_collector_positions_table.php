<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jejak GPS collector lapangan (live tracking). Ditulis tiap
        // 30-60 detik oleh HP collector, dibaca peta supervisor.
        // Retensi pendek (default 14 hari, lihat collectors:prune-positions)
        // agar tabel tidak membengkak.
        Schema::create('collector_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('debt_collectors')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('accuracy')->nullable()->comment('Akurasi GPS meter');
            $table->timestamp('recorded_at')->comment('Waktu di HP collector');
            $table->timestamps();
            $table->index(['collector_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_positions');
    }
};
