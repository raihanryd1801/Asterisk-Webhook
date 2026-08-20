<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('agents', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('extension')->unique(); // Misal: 101, 102
        $table->string('secret');              // Password PJSIP
        $table->string('workgroup')->nullable();
        $table->string('status')->default('offline'); // online, offline, in-call
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
