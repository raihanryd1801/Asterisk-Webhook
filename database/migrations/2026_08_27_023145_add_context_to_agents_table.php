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
        // Default selalu 'from-internal' agar agen baru bisa langsung nelpon
        $table->string('context', 50)->default('from-internal')->after('supervisor_id');
    });
}
public function down()
{
    Schema::table('agents', function (Blueprint $table) {
        $table->dropColumn('context');
    });
}
};
