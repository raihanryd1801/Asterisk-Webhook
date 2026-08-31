<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Drop foreign key dulu, baru drop kolomnya
            $table->dropForeign(['supervisor_id']);
            $table->dropColumn('supervisor_id');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->foreignId('supervisor_id')->nullable()->constrained('agents')->onDelete('set null');
        });
    }
};