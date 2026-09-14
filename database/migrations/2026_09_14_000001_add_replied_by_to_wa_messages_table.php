<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            // Audit siapa yang membalas dari sesi SPV bersama (Opsi B).
            $table->foreignId('replied_by_agent_id')->nullable()->after('customer_id')
                ->constrained('agents')->nullOnDelete();
            $table->string('replied_by_label', 120)->nullable()->after('replied_by_agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replied_by_agent_id');
            $table->dropColumn('replied_by_label');
        });
    }
};
