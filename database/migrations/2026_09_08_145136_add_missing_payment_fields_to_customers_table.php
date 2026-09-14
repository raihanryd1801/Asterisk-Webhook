<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'payment_date')) {
                $table->renameColumn('payment_date', 'last_payment_date');
            } elseif (!Schema::hasColumn('customers', 'last_payment_date')) {
                // Fresh install: kolom payment_date tidak pernah ada
                // (create table versi final langsung pakai last_payment_date).
                $table->timestamp('last_payment_date')->nullable();
            }
            if (!Schema::hasColumn('customers', 'payment_proof')) {
                $table->string('payment_proof')->nullable()->after('last_payment_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'payment_date')) {
                $table->renameColumn('last_payment_date', 'payment_date');
            }
            if (Schema::hasColumn('customers', 'payment_proof')) {
                $table->dropColumn('payment_proof');
            }
        });
    }
};