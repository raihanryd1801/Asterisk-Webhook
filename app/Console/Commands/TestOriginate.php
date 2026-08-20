<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Asterisk\OriginateService;

class TestOriginate extends Command
{
    // Cara pakainya nanti: php artisan call:test 101 08123456789
    protected $signature = 'call:test {agent} {target}';
    protected $description = 'Test fitur Click-to-Dial via AMI';

    public function handle(OriginateService $originateService)
    {
        $agent = $this->argument('agent');
        $target = $this->argument('target');

        $this->info("Meminta Asterisk menelpon Agent {$agent} untuk disambungkan ke {$target}...");

        try {
            $response = $originateService->clickToDial($agent, $target);
            $this->info("Berhasil dieksekusi!");
            $this->line("Balasan Asterisk: \n" . trim($response));
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}