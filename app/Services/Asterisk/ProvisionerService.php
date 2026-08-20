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
        $this->pass = env('ASTERISK_SSH_PASS', 'fid1234');
    }

    public function provision(Agent $agent)
    {
        \Log::info("=== [PROVISIONING] Menambahkan ekstensi: {$agent->extension} dengan Auto Recording (AstDB) ===");

        try {
            // 1. Buat CSV Bulk Import standar FreePBX
            $csvContent = "action,extension,name,tech,secret\n";
            $csvContent .= "add,{$agent->extension},\"{$agent->name}\",pjsip,{$agent->secret}\n";

            $remoteCsvPath = "/tmp/ext_{$agent->extension}.csv";

            $sftp = new SFTP($this->host);
            if (!$sftp->login($this->user, $this->pass)) {
                throw new Exception("Gagal login SFTP ke FreePBX");
            }
            $sftp->put($remoteCsvPath, $csvContent);

            $ssh = new SSH2($this->host);
            if (!$ssh->login($this->user, $this->pass)) {
                throw new Exception("Gagal login SSH ke FreePBX");
            }

            // 2. Jalankan Bulk Import ekstensi baru
            $importCommand = "fwconsole bulkimport --type=extensions {$remoteCsvPath}";
            $importOutput = $ssh->exec($importCommand);
            \Log::info("[PROVISION] Import Output: " . trim($importOutput));

            // 3. JURUS SAKTI ASTDB (Hasil Debugging Abang)
            \Log::info("[PROVISION] Mengeksekusi AstDB Recording untuk ekstensi {$agent->extension}...");
            $ext = $agent->extension;
            
            $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/in/external yes'");
            $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/out/external yes'");
            $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/in/internal yes'");
            $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/out/internal yes'");
            $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/ondemand enabled'");
            $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/recording/priority 10'");

            // 4. Reload FreePBX agar sinkron
            $reloadOutput = $ssh->exec("fwconsole reload");
            \Log::info("[PROVISION] Reload Output: " . trim($reloadOutput));

            // 5. Bersihkan file CSV sementara
            $sftp->delete($remoteCsvPath);

            return "Provisioning & Recording Setup Success";

        } catch (Exception $e) {
            \Log::error("[PROVISIONING EXCEPTION] " . $e->getMessage());
            throw new Exception("Error Provisioning: " . $e->getMessage());
        }
    }

   public function remove(Agent $agent)
    {
        \Log::info("=== [DELETE PABX] Memulai pembersihan total ekstensi: {$agent->extension} ==px");

        try {
            $sftp = new SFTP($this->host);
            if (!$sftp->login($this->user, $this->pass)) {
                throw new Exception("Gagal login SFTP ke FreePBX saat delete");
            }

            $ssh = new SSH2($this->host);
            if (!$ssh->login($this->user, $this->pass)) {
                throw new Exception("Gagal login SSH ke FreePBX saat delete");
            }

            $ext = $agent->extension;

            // 1. Buat script PHP untuk menghapus dari FreePBX Core & Database MySQL
            $phpScriptContent = "<?php
            include('/etc/freepbx.conf');
            global \$amp_conf;
            try {
                \$ext = '{$ext}';
                
                if (class_exists('FreePBX')) {
                    \$core = FreePBX::Core();
                    if (method_exists(\$core, 'delDevice')) { \$core->delDevice(\$ext); }
                    if (method_exists(\$core, 'delUser')) { \$core->delUser(\$ext); }
                }

                \$dsn = \"mysql:host=\" . \$amp_conf['AMPDBHOST'] . \";dbname=\" . \$amp_conf['AMPDBNAME'] . \";charset=utf8\";
                \$pdo = new PDO(\$dsn, \$amp_conf['AMPDBUSER'], \$amp_conf['AMPDBPASS']);
                
                // Hapus dari tabel relasi utama FreePBX & PJSIP
                \$tables = ['users', 'devices', 'ps_endpoints', 'ps_auths', 'ps_aors'];
                foreach (\$tables as \$table) {
                    \$stmt = \$pdo->prepare(\"DELETE FROM `\$table` WHERE id = ? OR extension = ?\");
                    \$stmt->execute([\$ext, \$ext]);
                }
                
                \$stmt = \$pdo->prepare(\"DELETE FROM ps_contacts WHERE id LIKE ?\");
                \$stmt->execute([\$ext . '%']);
                
                echo 'SUCCESS_DELETED';
            } catch (Exception \$e) {
                echo 'ERROR: ' . \$e->getMessage();
            }
            ";

            $remoteScriptPath = "/tmp/cleanup_ext_{$ext}.php";
            $sftp->put($remoteScriptPath, $phpScriptContent);

            // 2. Eksekusi script PHP pembersihan MySQL & Core
            \Log::info("[DELETE PABX] Menjalankan script PHP pembersihan database...");
            $output = $ssh->exec("php {$remoteScriptPath}");
            \Log::info("[DELETE PABX] Output Script: " . trim($output));

            // 3. KRUSIAL: Bersihkan cache AstDB internal Asterisk agar tidak nyangkut di PABX
            \Log::info("[DELETE PABX] Membersihkan AstDB cache Asterisk...");
            $ssh->exec("asterisk -rx 'database deltree AMPUSER/{$ext}'");
            $ssh->exec("asterisk -rx 'database deltree DEVICE/{$ext}'");
            $ssh->exec("asterisk -rx 'database deltree PJSIP/endpoints/{$ext}'");

            // 4. Jalankan fwconsole reload untuk merefresh konfigurasi PABX secara total
            \Log::info("[DELETE PABX] Menjalankan fwconsole reload...");
            $reloadOutput = $ssh->exec("fwconsole reload");
            \Log::info("[DELETE PABX] Output Reload: " . trim($reloadOutput));

            // 5. Hapus file script sementara di FreePBX
            $sftp->delete($remoteScriptPath);

            if (str_contains($output, 'ERROR')) {
                throw new Exception("FreePBX Cleanup Error: " . $output);
            }

            return "Delete & Cleanup Success";

        } catch (Exception $e) {
            \Log::error("[DELETE PABX EXCEPTION] " . $e->getMessage());
            throw new Exception("Error Deletion Provisioning: " . $e->getMessage());
        }
    }

    public function modify(Agent $agent, $secretChanged = false)
    {
        \Log::info("=== [UPDATE PABX] Mengupdate ekstensi: {$agent->extension} ===");

        try {
            $ssh = new SSH2($this->host);
            if (!$ssh->login($this->user, $this->pass)) {
                throw new Exception("Gagal login SSH ke FreePBX");
            }
            
            $sftp = new SFTP($this->host);
            if (!$sftp->login($this->user, $this->pass)) {
                throw new Exception("Gagal login SFTP ke FreePBX");
            }

            $ext = $agent->extension;
            // Gunakan addslashes untuk mencegah error jika nama ada tanda kutip (misal: d'Arc)
            $name = addslashes($agent->name);
            $secret = addslashes($agent->secret ?? '');
            $isSecretChanged = $secretChanged ? 'true' : 'false';

            // 1. Script PHP Bypass Database untuk Update Nama & Password di MySQL FreePBX
            $phpUpdateScript = "<?php
            include('/etc/freepbx.conf');
            global \$amp_conf;
            try {
                \$ext = '{$ext}';
                \$name = '{$name}';
                \$secret = '{$secret}';
                \$secretChanged = {$isSecretChanged};
                
                \$dsn = \"mysql:host=\" . \$amp_conf['AMPDBHOST'] . \";dbname=\" . \$amp_conf['AMPDBNAME'] . \";charset=utf8\";
                \$pdo = new PDO(\$dsn, \$amp_conf['AMPDBUSER'], \$amp_conf['AMPDBPASS']);
                
                // Update tabel users
                \$stmt = \$pdo->prepare(\"UPDATE users SET name = ? WHERE extension = ?\");
                \$stmt->execute([\$name, \$ext]);
                
                // Update tabel devices
                \$stmt = \$pdo->prepare(\"UPDATE devices SET description = ? WHERE id = ?\");
                \$stmt->execute([\$name, \$ext]);
                
                // Update callerid di PJSIP
                \$callerid = \$name . ' <' . \$ext . '>';
                \$stmt = \$pdo->prepare(\"UPDATE ps_endpoints SET callerid = ? WHERE id = ?\");
                \$stmt->execute([\$callerid, \$ext]);
                
                // Update password (hanya dieksekusi jika form password diisi)
                if (\$secretChanged && !empty(\$secret)) {
                    \$stmt = \$pdo->prepare(\"UPDATE ps_auths SET password = ? WHERE id = ?\");
                    \$stmt->execute([\$secret, \$ext]);
                }
                
                echo 'DB_UPDATED_SUCCESS';
            } catch (Exception \$e) {
                echo 'ERROR: ' . \$e->getMessage();
            }
            ";

            $remoteScriptPath = "/tmp/update_ext_{$ext}.php";
            $sftp->put($remoteScriptPath, $phpUpdateScript);
            
            // 2. Eksekusi Script PHP MySQL via SSH
            $output = $ssh->exec("php {$remoteScriptPath}");
            \Log::info("[UPDATE PABX] Database Output: " . trim($output));

            // 3. JURUS ASTDB: Update Nama di Internal Memori Asterisk
            \Log::info("[UPDATE PABX] Mengupdate AstDB cidname...");
            $ssh->exec("asterisk -rx 'database put AMPUSER {$ext}/cidname \"{$name}\"'");

            // 4. Reload FreePBX untuk sinkronisasi total
            $reloadOutput = $ssh->exec("fwconsole reload");
            \Log::info("[UPDATE PABX] Reload Output: " . trim($reloadOutput));
            
            // 5. Bersihkan script
            $sftp->delete($remoteScriptPath);

            return "Update Provisioning Success";

        } catch (Exception $e) {
            \Log::error("[UPDATE PABX EXCEPTION] " . $e->getMessage());
            throw new Exception("Error Update Provisioning: " . $e->getMessage());
        }
    }
}