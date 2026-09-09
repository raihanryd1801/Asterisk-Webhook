<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'handover_status')) {
                $table->enum('handover_status', ['none', 'ready', 'handed_over', 'returned'])->default('none')->after('promise_to_pay');
            }
            if (!Schema::hasColumn('customers', 'handover_to')) {
                $table->string('handover_to')->nullable()->after('handover_status')->comment('Nama pihak ketiga / agensi');
            }
            if (!Schema::hasColumn('customers', 'handover_date')) {
                $table->date('handover_date')->nullable()->after('handover_to');
            }
            if (!Schema::hasColumn('customers', 'handover_notes')) {
                $table->text('handover_notes')->nullable()->after('handover_date');
            }
        });

        if (!Schema::hasIndex('customers', 'customers_handover_status_index')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->index('handover_status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['handover_status', 'handover_to', 'handover_date', 'handover_notes']);
        });
    }
};