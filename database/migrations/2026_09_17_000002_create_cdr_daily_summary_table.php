<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ringkasan CDR per (tanggal, src, dst, disposition) untuk dashboard.
        // Overview membaca tabel kecil ini (ribuan baris) alih-alih cdr_live
        // (jutaan baris) sehingga tetap milidetik di skala berapa pun.
        Schema::create('cdr_daily_summary', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('src', 80)->index();
            $table->string('dst', 80)->index();
            $table->string('disposition', 45)->index();
            $table->unsignedInteger('calls')->default(0);
            $table->unsignedBigInteger('billsec')->default(0);
            $table->timestamps();

            $table->unique(['date', 'src', 'dst', 'disposition'], 'cdr_summary_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cdr_daily_summary');
    }
};
