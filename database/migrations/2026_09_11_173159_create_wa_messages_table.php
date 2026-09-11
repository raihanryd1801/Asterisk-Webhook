<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_messages', function (Blueprint $table) {
            $table->id();
            // Sesi pemilik: "user:{id}" (admin) atau "spv:{extension}" (supervisor)
            $table->string('session_id', 64);
            $table->enum('direction', ['in', 'out']);
            // Nomor lawan bicara, ternormalisasi format 62xxxxxxxxxx
            $table->string('phone', 20);
            $table->string('name')->nullable()->comment('pushName WA / nama customer bila cocok');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->text('message');
            $table->string('external_id')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'phone', 'occurred_at']);
            $table->index(['session_id', 'direction', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_messages');
    }
};