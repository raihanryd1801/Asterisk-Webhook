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
    Schema::table('agents', function (Blueprint $table) {
        // Menambahkan kolom role dengan default 'agent'
        $table->enum('role', ['agent', 'supervisor'])->default('agent')->after('extension');
    });
}

public function down()
{
    Schema::table('agents', function (Blueprint $table) {
        $table->dropColumn('role');
    });
}
};
