<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // e.g., EARLY-001, LATE-002
            $table->text('description')->nullable();
            $table->enum('type', ['early', 'late', 'legal', 'recovery'])->default('early');
            $table->json('target_buckets')->nullable()->comment('["Bucket 1", "Bucket 2"]');
            $table->json('channels')->nullable()->comment('["sms", "call", "visit", "email", "wa"]');
            $table->integer('max_attempts_per_day')->default(3);
            $table->time('start_time')->default('08:00:00');
            $table->time('end_time')->default('17:00:00');
            $table->json('schedule_days')->nullable()->comment('[1,2,3,4,5] = Mon-Fri');
            $table->text('script_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};