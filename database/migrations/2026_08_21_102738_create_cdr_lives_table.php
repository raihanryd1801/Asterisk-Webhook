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
    Schema::create('cdr_live', function (Blueprint $table) {
        $table->id();
        $table->dateTime('calldate')->index();
        $table->string('src', 80)->index();
        $table->string('dst', 80)->index();
        $table->string('dcontext', 80)->nullable();
        $table->string('channel', 80)->nullable();
        $table->string('dstchannel', 80)->nullable();
        $table->string('lastapp', 80)->nullable();
        $table->string('lastdata', 80)->nullable();
        $table->integer('duration')->default(0);
        $table->integer('billsec')->default(0);
        $table->string('disposition', 45)->index(); // ANSWERED, NO ANSWER, dll
        $table->string('amaflags', 45)->nullable();
        $table->string('accountcode', 20)->nullable();
        $table->string('uniqueid', 32)->unique();
        $table->string('userfield')->nullable();
        $table->string('recordingfile')->nullable();
        $table->string('cnum', 40)->nullable();
        $table->string('cnam', 40)->nullable();
        $table->string('outbound_cnum', 40)->nullable();
        $table->string('outbound_cnam', 40)->nullable();
        $table->string('dst_cnam', 40)->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cdr_lives');
    }
};
