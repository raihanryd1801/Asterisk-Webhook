<?php

namespace App\Services\Asterisk;

use App\Models\Agent;
use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;
use Exception;

class ProvisionerService
{
    protected $host;
    protected $user;
    protected $pass;

    public function __construct()
    {
        // Sesuaikan dengan IP dan root password FreePBX Abang (.env)
        $this->host = env('ASTERISK_SSH_HOST', '192.168.99.73'); 
        $this->user = env('ASTERISK_SSH_USER', 'root');
        $this->pass = env('ASTERISK_SSH_PASS', 'password_root_freepbx');
    }

    public function provision(Agent $agent)
    {
        try {
            // 1. Siapkan format CSV khusus FreePBX (Kolom: action, extension, name, tech, secret)
            $csvContent = "action,extension,name,tech,secret\n";
            $csvContent .= "add,{$agent->extension},\"{$agent->name}\",pjsip,{$agent->secret}\n";

            $remoteCsvPath = "/tmp/ext_{$agent->extension}.csv";

            // 2. Upload file CSV ke server FreePBX via SFTP
            $sftp = new SFTP($this->host);
            if (!$sftp->login($this->user, $this->pass)) {
                throw new Exception("Gagal login SFTP ke FreePBX");
            }
            $sftp->put($remoteCsvPath, $csvContent);

            // 3. Eksekusi perintah Bulk Import via SSH
            $ssh = new SSH2($this->host);
            if (!$ssh->login($this->user, $this->pass)) {
                throw new Exception("Gagal login SSH ke FreePBX");
            }

            // Perintah untuk memasukkan ekstensi ke database FreePBX
            $importCommand = "fwconsole bulkimport --type=extensions {$remoteCsvPath}";
            $importOutput = $ssh->exec($importCommand);

            // Perintah untuk me-reload/apply config Asterisk
            $reloadOutput = $ssh->exec("fwconsole reload");

            // Hapus file CSV di server PABX agar bersih
            $sftp->delete($remoteCsvPath);

            return "Import: \n{$importOutput}\nReload: \n{$reloadOutput}";

        } catch (Exception $e) {
            throw new Exception("Error Provisioning: " . $e->getMessage());
        }
    }
}