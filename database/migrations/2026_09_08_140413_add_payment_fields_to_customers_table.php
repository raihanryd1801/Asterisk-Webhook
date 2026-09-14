<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIREKONSTRUKSI (file asli terhapus dari repo, tapi tercatat sudah jalan
 * di database production). Isi disalin dari skema production agar
 * `migrate` fresh-install menghasilkan struktur yang sama persis.
 * Semua guarded hasColumn agar aman dijalankan di mana pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('customers', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('customers', 'discount_amount')) {
                $table->decimal('discount_amount', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('customers', 'payment_status')) {
                $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'discounted'])->default('unpaid');
            }
            if (!Schema::hasColumn('customers', 'payment_notes')) {
                $table->text('payment_notes')->nullable();
            }
            if (!Schema::hasColumn('customers', 'payment_date')) {
                $table->date('payment_date')->nullable();
            }
            if (!Schema::hasColumn('customers', 'due_date')) {
                $table->date('due_date')->nullable();
            }
            if (!Schema::hasColumn('customers', 'days_past_due')) {
                $table->integer('days_past_due')->default(0);
            }
            if (!Schema::hasColumn('customers', 'bucket')) {
                $table->string('bucket')->nullable();
            }
            if (!Schema::hasColumn('customers', 'campaign_id')) {
                $table->unsignedBigInteger('campaign_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            foreach (['total_amount', 'paid_amount', 'discount_amount', 'payment_status', 'payment_notes', 'payment_date', 'due_date', 'days_past_due', 'bucket', 'campaign_id'] as $col) {
                if (Schema::hasColumn('customers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
