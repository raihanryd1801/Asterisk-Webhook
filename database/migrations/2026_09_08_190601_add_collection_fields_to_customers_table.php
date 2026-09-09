<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Add only missing columns
            if (!Schema::hasColumn('customers', 'collector_id')) {
                $table->foreignId('collector_id')->nullable()->constrained('agents')->nullOnDelete()->after('campaign_id');
            }
            if (!Schema::hasColumn('customers', 'risk_level')) {
                $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low')->after('collector_id');
            }
            if (!Schema::hasColumn('customers', 'promise_to_pay')) {
                $table->json('promise_to_pay')->nullable()->after('risk_level')->comment('PTP: amount, date, note, status');
            }
            
            // Indexes (only if not exist)
            if (!Schema::hasIndex('customers', 'customers_bucket_days_past_due_index')) {
                $table->index(['bucket', 'days_past_due']);
            }
            if (!Schema::hasIndex('customers', 'customers_campaign_id_collector_id_index')) {
                $table->index(['campaign_id', 'collector_id']);
            }
            if (!Schema::hasIndex('customers', 'customers_risk_level_index')) {
                $table->index('risk_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropForeign(['collector_id']);
            $table->dropColumn([
                'collector_id', 'risk_level', 'promise_to_pay'
            ]);
        });
    }
};