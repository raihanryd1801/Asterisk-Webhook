<?php

namespace App\Console\Commands;

use App\Models\DialJob;
use App\Services\Asterisk\PdsDialService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PdsWorkCommand extends Command
{
    protected $signature = 'pds:work {--interval=4 : Jeda antar tick dalam detik}';
    protected $description = 'Worker daemon PDS: mendial antrian bucket ke agent yang join rotation & standby';

    public function handle(PdsDialService $pds)
    {
        // Single-instance: cegah 2 worker jalan barengan (double-dial)
        $lock = Cache::lock('pds-work-lock', 30);

        try {
            if (!$lock->acquire()) {
                $this->error('Worker PDS sudah berjalan di proses lain. Batal.');
                return 1;
            }

            $interval = max(2, (int) $this->option('interval'));
            $this->info("PDS worker jalan (tick tiap {$interval} dtk). Ctrl+C untuk berhenti.");

            while (true) {
                // Perpanjang lock tiap loop supaya tidak kedaluwarsa
                $lock->acquire();

                $jobs = DialJob::where('status', 'running')->orderBy('id')->get();

                if ($jobs->isEmpty()) {
                    $this->line('[' . now()->format('H:i:s') . '] Tidak ada job running. Menunggu...');
                }

                foreach ($jobs as $job) {
                    try {
                        $result = $pds->tick($job->fresh());

                        if ($result['dialed'] > 0) {
                            $this->line('[' . now()->format('H:i:s') . "] Job #{$job->id} '{$job->name}': {$result['dialed']} panggilan didial.");
                        } elseif (!empty($result['skipped_reason'])) {
                            $this->line('[' . now()->format('H:i:s') . "] Job #{$job->id}: {$result['skipped_reason']}");
                        }

                        if (!empty($result['completed'])) {
                            $this->info("Job #{$job->id} '{$job->name}' SELESAI (antrian habis).");
                        }
                    } catch (\Exception $e) {
                        $this->error("Job #{$job->id} error: " . $e->getMessage());
                    }
                }

                sleep($interval);
            }
        } finally {
            try {
                $lock->release();
            } catch (\Exception $e) {
                // abaikan
            }
        }
    }
}
