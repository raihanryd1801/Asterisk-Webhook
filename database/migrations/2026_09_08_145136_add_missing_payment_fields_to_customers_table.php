<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->renameColumn('payment_date', 'last_payment_date');
            $table->string('payment_proof')->nullable()->after('last_payment_date');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->renameColumn('last_payment_date', 'payment_date');
            $table->dropColumn('payment_proof');
        });
    }
};