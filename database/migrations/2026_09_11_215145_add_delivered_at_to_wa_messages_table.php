<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('wa_messages', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('read_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            if (Schema::hasColumn('wa_messages', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }
        });
    }
};