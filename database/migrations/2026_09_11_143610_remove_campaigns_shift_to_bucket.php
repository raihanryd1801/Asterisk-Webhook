<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Blast log pindah dari campaign_id ke bucket (baris lama: 0 rows, aman)
        $this->dropForeignIfExists('blast_logs', 'campaign_id');
        Schema::table('blast_logs', function (Blueprint $table) {
            if (Schema::hasColumn('blast_logs', 'campaign_id')) {
                $table->dropColumn('campaign_id');
            }
            if (!Schema::hasColumn('blast_logs', 'bucket')) {
                $table->string('bucket')->nullable()->after('id');
            }
        });

        // Customers: lepas FK campaign, hapus kolomnya
        $this->dropForeignIfExists('customers', 'campaign_id');
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'campaign_id')) {
                $table->dropColumn('campaign_id');
            }
        });

        Schema::dropIfExists('campaigns');
    }

    protected function dropForeignIfExists(string $table, string $column): void
    {
        $db = \Illuminate\Support\Facades\DB::getDatabaseName();
        $fk = \Illuminate\Support\Facades\DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($fk) {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`");
        }
    }

    public function down(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['early', 'late', 'legal', 'recovery'])->default('early');
            $table->json('target_buckets')->nullable();
            $table->json('channels')->nullable();
            $table->integer('max_attempts_per_day')->default(3);
            $table->time('start_time')->default('08:00:00');
            $table->time('end_time')->default('17:00:00');
            $table->json('schedule_days')->nullable();
            $table->text('script_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('blast_logs', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete()->after('id');
            if (Schema::hasColumn('blast_logs', 'bucket')) {
                $table->dropColumn('bucket');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
        });
    }
};