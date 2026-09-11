<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agent yang JOIN rotasi PDS = siap menerima panggilan otomatis.
        // Join = consent auto-answer. Tanpa 1 pun member, PDS tidak jalan.
        Schema::create('pds_rotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->unique()->constrained('agents')->cascadeOnDelete();
            $table->string('extension', 20);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_rotations');
    }
};