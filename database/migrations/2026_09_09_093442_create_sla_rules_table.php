<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_rules', function (Blueprint $table) {
            $table->id();
            $table->string('bucket'); // Current, Bucket 1, Bucket 2, Bucket 3, NPL
            $table->unsignedInteger('max_dpd'); // breach jika days_past_due > max_dpd
            $table->enum('escalate_risk', ['low', 'medium', 'high', 'critical']);
            $table->string('action_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('bucket');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_rules');
    }
};