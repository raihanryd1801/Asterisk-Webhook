#!/usr/bin/env php
<?php
/**
 * pds-bridge.php — AGI untuk context [pds-connect] (PDS customer-first).
 *
 * BUKAN bagian runtime Laravel. Cara pakai: copy file ini ke
 * /var/lib/asterisk/agi-bin/ di SERVER ASTERISK (FreePBX), lalu:
 *   chown asterisk:asterisk /var/lib/asterisk/agi-bin/pds-bridge.php
 *   chmod +x /var/lib/asterisk/agi-bin/pds-bridge.php
 *
 * Konfigurasi via environment (diarterisk: /etc/asterisk/environment atau
 * export sebelum asterisk start — paling gampang hardcode 2 baris bawah):
 *   PDS_LARAVEL_URL    mis. http://172.16.1.10:8000
 *   PDS_BRIDGE_TOKEN   sama dengan PDS_BRIDGE_TOKEN di .env Laravel
 *
 * Cara kerja:
 *  1. Baca variable channel PDS_JOB / PDS_ITEM / AMDSTATUS (dialplan).
 *  2. POST ke Laravel /api/pds/bridge (timeout 8 dtk).
 *  3. SET VARIABLE AGENT_EXT "<ext>" (atau "" bila kosong/gagal).
 * Dialplan: GotoIf($["${AGENT_EXT}" = ""]?noagent) -> Dial(PJSIP/${AGENT_EXT}).
 */

$laravel = getenv('PDS_LARAVEL_URL') ?: 'http://127.0.0.1:8000';
$token = getenv('PDS_BRIDGE_TOKEN') ?: '';

// --- 1. Baca environment AGI dari stdin ---
$agi = [];
while ($line = fgets(STDIN)) {
    $line = trim($line);
    if ($line === '') {
        break;
    }
    if (str_starts_with($line, 'agi_')) {
        [$k, $v] = explode(':', $line, 2) + [null, ''];
        $agi[trim($k)] = trim($v);
    }
}

function agi_cmd($cmd)
{
    fwrite(STDOUT, $cmd . "\n");
    fflush(STDOUT);
    $res = fgets(STDIN);
    return $res === false ? '' : trim($res);
}

function agi_get_fullvar($name)
{
    $res = agi_cmd('GET FULL VARIABLE ' . $name);
    // Balas: 200 result=1 (Budi Santoso)
    if (preg_match('/result=1\s*\((.*)\)\s*$/', $res, $m)) {
        return $m[1];
    }
    return '';
}

$job = agi_get_fullvar('PDS_JOB');
$item = agi_get_fullvar('PDS_ITEM');
$amd = agi_get_fullvar('AMDSTATUS');

$agentExt = '';

// --- 2. Tanya Laravel (hanya bila item dikenal) ---
if ($job !== '' && $item !== '' && $token !== '') {
    $payload = json_encode(['job_id' => (int) $job, 'item_id' => (int) $item, 'amd' => $amd]);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nX-Pds-Token: {$token}\r\n",
            'content' => $payload,
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents(rtrim($laravel, '/') . '/api/pds/bridge', false, $ctx);
    if ($raw !== false) {
        $data = json_decode($raw, true);
        if (is_array($data) && ($data['status'] ?? '') === 'success') {
            $agentExt = preg_replace('/\D/', '', (string) ($data['agent_ext'] ?? ''));
        }
    }
}

// --- 3. Kembalikan ke dialplan ---
agi_cmd('SET VARIABLE AGENT_EXT "' . $agentExt . '"');
exit(0);
