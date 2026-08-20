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
        $table->string('name'); // Nama Grup (Contoh: Support, Sales)
        $table->string('queue_number')->nullable(); // Nomor antrian FreePBX
        $table->timestamps();
    });

    // Pastikan juga kolom group_id ada di tabel agents
    Schema::table('agents', function (Blueprint $table) {
        if (!Schema::hasColumn('agents', 'group_id')) {
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
        }
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
