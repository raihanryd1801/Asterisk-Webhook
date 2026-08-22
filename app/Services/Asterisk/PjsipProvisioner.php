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
        $this->host = env('ASTERISK_SSH_HOST', env('FREEPBX_SSH_HOST', '192.168.99.73')); 
        $this->user = env('ASTERISK_SSH_USER', env('FREEPBX_SSH_USER', 'root'));
        $this->pass = env('ASTERISK_SSH_PASS', env('FREEPBX_SSH_PASS', 'fid1234'));
    }

    /**
     * 1. TAMBAH AGEN (Menggunakan CSV Bulk Import yang sudah terbukti jalan)
     */
    public function provision(Agent $agent)
    {
        \Log::info("=== [PROVISIONING] Menambahkan ekstensi: {$agent->extension} ===");

        try {
            $csvContent = "action,extension,name,tech,secret,record_in,record_out,record_local,record_local_out,record_ondemand\n";
            $csvContent .= "add,{$agent->extension},\"{$agent->name}\",pjsip,{$agent->secret},yes,yes,yes,yes,enabled\n";

            $remoteCsvPath = "/tmp/ext_{$agent->extension}.csv";

            $sftp = new SFTP($this->host);
            if (!$sftp->login($this->user, $this->pass)) {
                throw new Exception("Gagal login SFTP ke FreePBX");
            }
            $sftp->put($remoteCsvPath, $csvContent);

            $ssh = $this->connectSsh();
            $importCommand = "fwconsole bulkimport --type=extensions {$remoteCsvPath}";
            $importOutput = $ssh->exec($importCommand);
            \Log::info("[PROVISION] Import Output: " . trim($importOutput));

            $ext = $agent->extension;
            
            $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/in/external yes'");
            $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/out/external yes'");
            $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/in/internal yes'");
            $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/out/internal yes'");
            $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/ondemand enabled'");
            $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/priority 10'");

            $ssh->exec("fwconsole reload");
            $sftp->delete($remoteCsvPath);

            return "Provisioning & Recording Setup Success";

        } catch (Exception $e) {
            \Log::error("[PROVISIONING EXCEPTION] " . $e->getMessage());
            throw new Exception("Error Provisioning: " . $e->getMessage());
        }
    }

    /**
     * 2. HAPUS AGEN (Bypass BMO Error, aman untuk PHP lama)
     */
    public function remove(Agent $agent)
    {
        \Log::info("=== [DELETE PABX] Memulai pembersihan total ekstensi: {$agent->extension} ===");

        try {
            $ext = $agent->extension;

            $phpScriptContent = "<?php
            try {
                \$config = @parse_ini_file('/etc/amportal.conf');
                \$host = isset(\$config['AMPDBHOST']) ? \$config['AMPDBHOST'] : 'localhost';
                \$dbname = isset(\$config['AMPDBNAME']) ? \$config['AMPDBNAME'] : 'asterisk';
                \$user = isset(\$config['AMPDBUSER']) ? \$config['AMPDBUSER'] : 'asteriskuser';
                \$pass = isset(\$config['AMPDBPASS']) ? \$config['AMPDBPASS'] : '';

                \$dsn = \"mysql:host=\$host;dbname=\$dbname;charset=utf8\";
                \$pdo = new PDO(\$dsn, \$user, \$pass);
                
                \$ext = '{$ext}';
                
                \$pdo->prepare(\"DELETE FROM users WHERE extension = ?\")->execute([\$ext]);
                \$pdo->prepare(\"DELETE FROM devices WHERE id = ?\")->execute([\$ext]);
                \$pdo->prepare(\"DELETE FROM ps_endpoints WHERE id = ?\")->execute([\$ext]);
                \$pdo->prepare(\"DELETE FROM ps_auths WHERE id = ? OR id = ?\")->execute([\$ext, \$ext . '-auth']);
                \$pdo->prepare(\"DELETE FROM ps_aors WHERE id = ?\")->execute([\$ext]);
                \$pdo->prepare(\"DELETE FROM ps_contacts WHERE id LIKE ?\")->execute([\$ext . '%']);
                
                echo 'SUCCESS_DELETED';
            } catch (Exception \$e) {
                echo 'ERROR: ' . \$e->getMessage();
            }
            ";

            $output = $this->runRemotePhp($phpScriptContent, "cleanup_{$ext}");
            \Log::info("[DELETE PABX] Output Script: " . trim($output));

            $ssh = $this->connectSsh();
            $ssh->exec("asterisk -rx 'database deltree AMPUSER/{$ext}'");
            $ssh->exec("asterisk -rx 'database deltree DEVICE/{$ext}'");
            $ssh->exec("asterisk -rx 'database deltree PJSIP/endpoints/{$ext}'");
            $ssh->exec("fwconsole reload");

            if (str_contains($output, 'ERROR')) {
                throw new Exception("FreePBX Cleanup Error: " . $output);
            }

            return "Delete & Cleanup Success";

        } catch (Exception $e) {
            \Log::error("[DELETE PABX EXCEPTION] " . $e->getMessage());
            throw new Exception("Error Deletion Provisioning: " . $e->getMessage());
        }
    }

    /**
     * 3. UPDATE AGEN (Bypass BMO Error, aman untuk PHP lama)
     */
    public function modify(Agent $agent, $secretChanged = false)
    {
        \Log::info("=== [UPDATE PABX] Mengupdate ekstensi: {$agent->extension} ===");

        try {
            $ext = $agent->extension;
            $name = addslashes($agent->name);
            $secret = addslashes($agent->secret ?? '');
            $updateSecret = $secretChanged ? 'true' : 'false';

            $phpUpdateScript = "<?php
            try {
                \$config = @parse_ini_file('/etc/amportal.conf');
                \$host = isset(\$config['AMPDBHOST']) ? \$config['AMPDBHOST'] : 'localhost';
                \$dbname = isset(\$config['AMPDBNAME']) ? \$config['AMPDBNAME'] : 'asterisk';
                \$user = isset(\$config['AMPDBUSER']) ? \$config['AMPDBUSER'] : 'asteriskuser';
                \$pass = isset(\$config['AMPDBPASS']) ? \$config['AMPDBPASS'] : '';

                \$dsn = \"mysql:host=\$host;dbname=\$dbname;charset=utf8\";
                \$pdo = new PDO(\$dsn, \$user, \$pass);
                
                \$ext = '{$ext}';
                \$name = '{$name}';
                \$secret = '{$secret}';
                \$updateSecret = {$updateSecret};
                
                \$pdo->prepare(\"UPDATE users SET name = ? WHERE extension = ?\")->execute([\$name, \$ext]);
                \$pdo->prepare(\"UPDATE devices SET description = ? WHERE id = ?\")->execute([\$name, \$ext]);
                
                \$callerid = \$name . ' <' . \$ext . '>';
                \$pdo->prepare(\"UPDATE ps_endpoints SET callerid = ? WHERE id = ?\")->execute([\$callerid, \$ext]);
                
                if (\$updateSecret && !empty(\$secret)) {
                    \$pdo->prepare(\"UPDATE ps_auths SET password = ? WHERE id = ? OR id = ?\")->execute([\$secret, \$ext, \$ext . '-auth']);
                }
                
                echo 'SUCCESS_UPDATE';
            } catch (Exception \$e) {
                echo 'ERROR: ' . \$e->getMessage();
            }
            ";

            $output = $this->runRemotePhp($phpUpdateScript, "update_{$ext}");
            \Log::info("[UPDATE PABX] Database Output: " . trim($output));

            if (str_contains($output, 'ERROR')) {
                throw new Exception("FreePBX Database Update Error: " . $output);
            }

            $ssh = $this->connectSsh();
            $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/cidname \"{$name}\"'");
            $ssh->exec("asterisk -rx 'module reload res_pjsip.so'");
            $ssh->exec("fwconsole reload");

            return "Update Provisioning Success";

        } catch (Exception $e) {
            \Log::error("[UPDATE PABX EXCEPTION] " . $e->getMessage());
            throw new Exception("Error Update Provisioning: " . $e->getMessage());
        }
    }

    /**
     * Helper koneksi SSH
     */
    private function connectSsh()
    {
        $ssh = new SSH2($this->host);
        if (!$ssh->login($this->user, $this->pass)) {
            throw new Exception("Gagal login SSH ke FreePBX");
        }
        return $ssh;
    }

    /**
     * Helper eksekusi script PHP aman
     */
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