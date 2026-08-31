<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_supervisor', function (Blueprint $table) {
    $table->id();
    $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
    $table->foreignId('supervisor_id')->constrained('agents')->cascadeOnDelete(); // Sesuaikan nama tabel SPV Abang
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_supervisor');
    }
};
