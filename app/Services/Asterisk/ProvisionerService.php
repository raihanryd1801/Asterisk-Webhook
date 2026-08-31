<?php

namespace App\Services\Asterisk;

use App\Models\Agent;
use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;
use Exception;
use Illuminate\Support\Facades\Log;

class ProvisionerService
{
    protected $host;
    protected $user;
    protected $pass;

    public function __construct()
    {
        $this->host = config('services.freepbx.host'); 
        $this->user = config('services.freepbx.user');
        $this->pass = config('services.freepbx.pass');
    }

    public function provision($agent)
    {
        $ext = is_object($agent) ? $agent->extension : $agent;
        Log::info("=== [PROVISIONING] Menambahkan ekstensi: {$ext} ===");
        return $this->executeBulkAction($agent, 'add');
    }

    public function modify($agent, $secretChanged = false)
    {
        $ext = is_object($agent) ? $agent->extension : $agent;
        Log::info("=== [UPDATE PABX] Mengupdate ekstensi: {$ext} ===");
        return $this->executeBulkAction($agent, 'edit');
    }

    public function remove($agent)
    {
        $ext = is_object($agent) ? $agent->extension : $agent;
        Log::info("=== [DELETE PABX] Menghapus ekstensi: {$ext} ===");
        
        try {
            $phpScriptContent = "<?php
            include('/etc/freepbx.conf');
            try {
                \$bmo = \FreePBX::Create();
                \$core = \$bmo->Core;
                \$ext = '{$ext}';
                
                if (method_exists(\$core, 'delDevice')) { 
                    \$core->delDevice(\$ext); 
                }
                if (method_exists(\$core, 'delUser')) { 
                    \$core->delUser(\$ext); 
                }
                
                echo 'SUCCESS_DELETED';
            } catch (Exception \$e) {
                echo 'ERROR: ' . \$e->getMessage();
            }
            ";

            $output = $this->runRemotePhp($phpScriptContent, "delete_{$ext}");
            Log::info("[DELETE PABX] BMO Output: " . trim($output));

            if (str_contains($output, 'ERROR')) {
                throw new Exception("FreePBX Delete Error: " . $output);
            }

            $ssh = $this->connectSsh();
            $ssh->exec("asterisk -rx 'database deltree AMPUSER/{$ext}'");
            $ssh->exec("asterisk -rx 'database deltree DEVICE/{$ext}'");
            $ssh->exec("asterisk -rx 'database deltree PJSIP/endpoints/{$ext}'");
            
            // Bersihkan juga dari AstDB CRM_BLOCK saat dihapus
            $ssh->exec("asterisk -rx 'database del CRM_BLOCK {$ext}'");
            
            $ssh->exec("fwconsole reload");

            return "Delete Success";

        } catch (Exception $e) {
            Log::error("[DELETE EXCEPTION] " . $e->getMessage());
            throw new Exception("Gagal menghapus PABX: " . $e->getMessage());
        }
    }

    private function executeBulkAction($agent, $action)
    {
        try {
            $ext = is_object($agent) ? $agent->extension : $agent;
            $name = (is_object($agent) && isset($agent->name)) ? $agent->name : 'Agent';
            $secret = (is_object($agent) && isset($agent->secret)) ? $agent->secret : '123456';

            // 1. Bulk import data dasar untuk mendaftarkan ekstensi & device
            $csvContent = "action,extension,name,tech,secret\n";
            $csvContent .= "{$action},{$ext},\"{$name}\",pjsip,{$secret}\n";

            $remoteCsvPath = "/tmp/ext_{$ext}_{$action}.csv";

            $sftp = new SFTP($this->host);
            if (!$sftp->login($this->user, $this->pass)) {
                throw new Exception("Gagal login SFTP ke FreePBX");
            }
            $sftp->put($remoteCsvPath, $csvContent);

            $ssh = $this->connectSsh();
            $command = "fwconsole bulkimport --type=extensions --replace {$remoteCsvPath}";
            $ssh->exec($command);
            $sftp->delete($remoteCsvPath);

            if ($action !== 'del') {
                // 2. Reload awal agar ekstensi terdaftar di memori FreePBX
                $ssh->exec("fwconsole reload");

                // 3. Set AstDB Recording langsung via Asterisk CLI (Jalur AstDB Asli)
                $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/in/external yes'");
                $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/out/external yes'");
                $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/in/internal yes'");
                $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/out/internal yes'");
                $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/ondemand enabled'");
                $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/priority 10'");

                // 4. Finalisasi CallerID AstDB
                $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/cidname \"{$name}\"'");

                // 🚀 5. KONTROL STATUS BLOKIR (ENABLE / DISABLE) VIA ASTDB CRM_BLOCK
                $context = (is_object($agent) && isset($agent->context)) ? $agent->context : 'from-internal';
                if ($context === 'blokir-total') {
                    $ssh->exec("asterisk -rx 'database put CRM_BLOCK {$ext} 1'");
                    Log::info("[ASTDB] Ekstensi {$ext} berhasil DIBLOKIR total (Outbound Block).");
                } else {
                    $ssh->exec("asterisk -rx 'database del CRM_BLOCK {$ext}'");
                    Log::info("[ASTDB] Blokir ekstensi {$ext} DICABUT (Enabled kembali).");
                }

                $ssh->exec("fwconsole reload");
                $ssh->exec("module reload res_pjsip.so");
            }

            return "Bulk Action {$action} Success";

        } catch (Exception $e) {
            Log::error("[BULK EXCEPTION] " . $e->getMessage());
            throw new Exception("Gagal sinkronisasi PABX ({$action}): " . $e->getMessage());
        }
    }

    private function connectSsh()
    {
        $ssh = new SSH2($this->host);
        if (!$ssh->login($this->user, $this->pass)) {
            throw new Exception("Gagal login SSH ke FreePBX");
        }
        return $ssh;
    }

    private function runRemotePhp($phpContent, $tag)
    {
        $ssh = $this->connectSsh();
        $remotePath = "/tmp/crm_{$tag}_" . time() . ".php";
        
        $escapedContent = base64_encode($phpContent);
        $ssh->exec("echo '{$escapedContent}' | base64 -d > {$remotePath}");

        $output = $ssh->exec("php {$remotePath}");
        $ssh->exec("rm -f {$remotePath}");

        return trim($output);
    }
}