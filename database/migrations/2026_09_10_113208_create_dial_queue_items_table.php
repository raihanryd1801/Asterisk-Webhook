<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dial_queue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('dial_jobs')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('phone', 30);
            $table->string('bucket')->nullable();
            $table->enum('status', ['queued', 'dialing', 'done', 'failed', 'skipped'])->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('agent_extension', 20)->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['job_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dial_queue_items');
    }
};