<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Services\Asterisk\ProvisionerService;
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
    public $action;
    public $secretChanged;

    public function __construct($agent, $action = 'create', $secretChanged = false)
    {
        $this->agent = $agent;
        $this->action = $action;
        $this->secretChanged = $secretChanged;
    }

    public function handle(ProvisionerService $provisioner): void
    {
        try {
            if ($this->action === 'create') {
                $output = $provisioner->provision($this->agent);
                Log::info("Provisioning Agent {$this->agent->extension} SUKSES: \n" . trim($output));
            } 
            elseif ($this->action === 'update') {
                $output = $provisioner->modify($this->agent, $this->secretChanged);
                Log::info("Update Agent {$this->agent->extension} SUKSES: \n" . trim($output));
            } 
            elseif ($this->action === 'delete') {
                $output = $provisioner->remove($this->agent);
                Log::info("Hapus Agent dari PABX SUKSES: \n" . trim($output));
            }

        } catch (\Exception $e) {
            Log::error("Gagal Proses Background PABX: " . $e->getMessage());
        }
    }
}