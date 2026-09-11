<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dial_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // {"Bucket 1": 10, "Bucket 2": 20} = kuota nomor yang ditarik per bucket
            $table->json('buckets_config');
            // Ratio: jumlah nomor disiapkan per 1 agent standby (mis. 2 = 1:2)
            $table->unsignedTinyInteger('lines_per_agent')->default(2);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->enum('status', ['draft', 'running', 'paused', 'completed', 'stopped'])->default('draft');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dial_jobs');
    }
};