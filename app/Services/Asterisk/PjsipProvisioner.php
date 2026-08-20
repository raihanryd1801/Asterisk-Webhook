<?php

namespace App\Services\Asterisk;

use App\Models\Agent;
use Exception;
use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;

class PjsipProvisioner
{
    protected $host;
    protected $user;
    protected $pass;

    public function __construct()
    {
        $this->host = env('FREEPBX_SSH_HOST');
        $this->user = env('FREEPBX_SSH_USER');
        $this->pass = env('FREEPBX_SSH_PASS');
    }

    /**
     * Generate file CSV, upload ke FreePBX via SFTP, lalu eksekusi fwconsole
     */
    public function provision(Agent $agent)
    {
        // Format CSV Bulk Handler FreePBX yang lebih bersih
        $csvContent = implode("\n", [
            'action,extension,name,tech,secret,record_in,record_out,record_local,record_local_out,record_ondemand',
            "add,{$agent->extension},{$agent->name},pjsip,{$agent->secret},yes,yes,yes,yes,enabled"
        ]) . "\n";

        $filename = "provision_{$agent->extension}.csv";
        $remotePath = "/tmp/{$filename}";

        try {
            $sftp = new SFTP($this->host);
            if (!$sftp->login($this->user, $this->pass)) {
                throw new Exception("Gagal login SFTP ke FreePBX.");
            }
            $sftp->put($remotePath, $csvContent);

            $ssh = new SSH2($this->host);
            if (!$ssh->login($this->user, $this->pass)) {
                throw new Exception("Gagal login SSH ke FreePBX.");
            }

            $command = "fwconsole bulkimport --type=extensions {$remotePath} && fwconsole reload";
            $output = $ssh->exec($command);

            $sftp->delete($remotePath);

            return $output;

        } catch (Exception $e) {
            throw new Exception("Error Provisioning PJSIP: " . $e->getMessage());
        }
    }
}