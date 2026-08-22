<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Services\Asterisk\PjsipProvisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionAsteriskAgent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $agent;

    /**
     * Create a new job instance.
     */
    public function __construct(Agent $agent)
    {
        $this->agent = $agent;
    }

    /**
     * Execute the job.
     * Inject PjsipProvisioner langsung ke method handle
     */
    public function handle(PjsipProvisioner $provisioner): void
    {
        try {
            // Eksekusi SSH dan SFTP berjalan di background
            $output = $provisioner->provision($this->agent);
            
            // Catat hasil output ke file log Laravel (storage/logs/laravel.log)
            Log::info("Provisioning Agent {$this->agent->extension} SUKSES: \n" . trim($output));

        } catch (\Exception $e) {
            // Catat jika terjadi error saat SSH
            Log::error("Gagal Provisioning Agent {$this->agent->extension}: " . $e->getMessage());
        }
    }
}