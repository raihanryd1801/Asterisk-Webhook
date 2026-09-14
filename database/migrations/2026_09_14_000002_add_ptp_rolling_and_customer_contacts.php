<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('gender', 20)->nullable()->after('company');
            $table->string('personal_phone', 30)->nullable()->after('phone');
            $table->string('office_phone', 30)->nullable()->after('personal_phone');
            $table->string('emergency_phone', 30)->nullable()->after('office_phone');
        });

        // Status PTP model lama -> baru: pending => new, broken => rolling.
        // Dijalankan per-batch agar aman untuk data besar.
        DB::table('customers')->whereNotNull('promise_to_pay')
            ->orderBy('id')->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $ptp = is_string($row->promise_to_pay)
                        ? json_decode($row->promise_to_pay, true)
                        : (array) $row->promise_to_pay;
                    if (!is_array($ptp) || empty($ptp['status'])) {
                        continue;
                    }
                    $map = ['pending' => 'new', 'broken' => 'rolling'];
                    if (!isset($map[$ptp['status']])) {
                        continue;
                    }
                    $ptp['status'] = $map[$ptp['status']];
                    $ptp['extend_count'] = $ptp['extend_count'] ?? 0;
                    DB::table('customers')->where('id', $row->id)
                        ->update(['promise_to_pay' => json_encode($ptp)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['gender', 'personal_phone', 'office_phone', 'emergency_phone']);
        });
    }
};
