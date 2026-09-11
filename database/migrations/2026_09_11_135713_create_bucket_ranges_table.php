<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bucket_ranges', function (Blueprint $table) {
            $table->id();
            $table->string('bucket')->unique();
            // DPD dalam hari (>= 0). max_dpd NULL = tanpa batas atas (bucket terakhir).
            $table->unsignedInteger('min_dpd')->default(0);
            $table->unsignedInteger('max_dpd')->nullable();
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bucket_ranges');
    }
};