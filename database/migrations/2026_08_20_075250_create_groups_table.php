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
        Schema::create('groups', function (Blueprint $table) {
    $table->id();
    $table->string('name'); // Nama Grup (contoh: CS-Support, Sales)
    $table->string('queue_number'); // Nomor antrian di Asterisk
    $table->timestamps();
});

// Tambahkan group_id ke tabel agents
Schema::table('agents', function (Blueprint $table) {
    $table->foreignId('group_id')->nullable()->constrained();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
