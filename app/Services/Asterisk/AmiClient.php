<?php

namespace App\Services\Asterisk;

use Exception;

class AmiClient
{
    protected $socket;
    protected $host;
    protected $port;
    protected $user;
    protected $pass;

    public function __construct()
    {
        $this->host = env('ASTERISK_AMI_HOST');
        $this->port = env('ASTERISK_AMI_PORT', 5038);
        $this->user = env('ASTERISK_AMI_USER');
        $this->pass = env('ASTERISK_AMI_PASS');
    }

    /**
     * Membuka koneksi socket ke AMI dan melakukan Login
     */
    public function connect()
    {
        // Buka socket dengan timeout 10 detik
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 10);

        if (!$this->socket) {
            throw new Exception("Gagal terhubung ke AMI FreePBX: $errstr ($errno)");
        }

        stream_set_timeout($this->socket, 5);

        // Kirim perintah Login
        $this->sendAction('Login', [
            'Username' => $this->user,
            'Secret' => $this->pass,
        ]);

        // Baca response login
        $response = $this->readResponse();
        
        if (!str_contains($response, 'Authentication accepted')) {
            throw new Exception("Login AMI Gagal! Cek kredensial di .env. Response: \n" . $response);
        }
    }

    /**
     * Kirim perintah (Action) ke Asterisk Manager Interface
     */
    public function sendAction($action, $parameters = [])
    {
        if (!$this->socket) {
            throw new Exception("Socket AMI belum terhubung! Panggil connect() dulu.");
        }

        $command = "Action: $action\r\n";
        foreach ($parameters as $key => $value) {
            $command .= "$key: $value\r\n";
        }
        $command .= "\r\n"; // Baris kosong menandakan akhir dari block Action

        fwrite($this->socket, $command);
    }

    /**
     * Baca balasan dari Asterisk
     */
    public function readResponse()
    {
        $response = '';
        while ($line = fgets($this->socket)) {
            $response .= $line;
            // Asterisk memisahkan tiap response/event dengan baris kosong (\r\n)
            if (trim($line) === '') {
                break; 
            }
        }
        return $response;
    }

    /**
     * Tutup koneksi dengan baik (Logoff)
     */
    public function disconnect()
    {
        if ($this->socket) {
            $this->sendAction('Logoff');
            fclose($this->socket);
        }
    }
    /**
     * Membaca event stream dari Asterisk secara terus-menerus tanpa putus karena timeout
     */
    public function listenToEvents(callable $callback)
    {
        while (!feof($this->socket)) {
            $line = fgets($this->socket);

            // Cek apakah socket mengalami timeout karena sepi event
            $meta = stream_get_meta_data($this->socket);
            if (!empty($meta['timed_out'])) {
                continue; // Lanjutkan loop, jangan dimatikan
            }

            if ($line === false) {
                break;
            }

            $trimmed = trim($line);
            // Setiap kali Asterisk mengirim baris Event
            if (str_starts_with($trimmed, 'Event:')) {
                $eventData = [];
                $eventData['Event'] = trim(substr($trimmed, 6));

                // Baca detail di bawah baris Event sampai baris kosong
                while ($subline = fgets($this->socket)) {
                    if (trim($subline) === '') {
                        break;
                    }
                    if (str_contains($subline, ':')) {
                        [$key, $value] = explode(':', $subline, 2);
                        $eventData[trim($key)] = trim($value);
                    }
                }

                // Jalankan callback untuk memproses data event
                call_user_func($callback, $eventData);
            }
        }
    }
}