<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('wa_messages', 'jid_server')) {
                $table->string('jid_server', 20)->default('s.whatsapp.net')->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            if (Schema::hasColumn('wa_messages', 'jid_server')) {
                $table->dropColumn('jid_server');
            }
        });
    }
};