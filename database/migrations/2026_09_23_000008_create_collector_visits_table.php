<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kunjungan collector ke customer (OTW -> sampai -> selesai/batal).
        // Kontrak API v1 untuk tombol OTW di aplikasi Flutter.
        Schema::create('collector_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('debt_collectors')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('status', 16)->default('otw')->comment('otw|sampai|selesai|batal');
            $table->string('result', 32)->nullable()->comment('bayar|ptp|janji|zonk|lainnya');
            $table->text('note')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['collector_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_visits');
    }
};
