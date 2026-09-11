<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_collectors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->enum('type', ['field', 'desk'])->default('field')->comment('field = lapangan, desk = telepon (internal)');
            $table->string('area')->nullable()->comment('Wilayah kerja collector lapangan');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Pindahkan FK customers.collector_id dari agents ke debt_collectors.
        // Nilai lama (id agent) tidak valid di tabel baru → null-kan dulu.
        Schema::table('customers', function (Blueprint $table) {
            try { $table->dropForeign(['collector_id']); } catch (\Throwable $e) {}
        });
        \Illuminate\Support\Facades\DB::table('customers')->update(['collector_id' => null]);
        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('collector_id')->references('id')->on('debt_collectors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            try { $table->dropForeign(['collector_id']); } catch (\Throwable $e) {}
        });
        \Illuminate\Support\Facades\DB::table('customers')->update(['collector_id' => null]);
        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('collector_id')->references('id')->on('agents')->nullOnDelete();
        });
        Schema::dropIfExists('debt_collectors');
    }
};