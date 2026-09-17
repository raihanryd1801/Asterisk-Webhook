<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Koreksi grain: (date, src, dst, disposition) menghasilkan baris
        // sebanyak call aslinya karena dst unik per panggilan. Data produksi
        // membuktikan sisi dst praktis tidak pernah extension agent
        // (1 dari 2,6 jt), jadi grain (date, src, disposition) sudah tepat:
        // ~ribuan baris, bukan jutaan.
        Schema::dropIfExists('cdr_daily_summary');
        Schema::create('cdr_daily_summary', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('src', 80)->index();
            $table->string('disposition', 45)->index();
            $table->unsignedInteger('calls')->default(0);
            $table->unsignedBigInteger('billsec')->default(0);
            $table->timestamps();

            $table->unique(['date', 'src', 'disposition'], 'cdr_summary_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cdr_daily_summary');
    }
};
