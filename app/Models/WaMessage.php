<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaMessage extends Model
{
    protected $fillable = [
        'session_id', 'direction', 'phone', 'jid_server', 'name', 'customer_id',
        'message', 'media_path', 'media_mime', 'external_id', 'occurred_at', 'read_at',
    ];

    public function getMediaUrlAttribute()
    {
        // Relatif (bukan asset()) agar tidak tergantung APP_URL yang masih localhost
        return $this->media_path ? '/storage/' . ltrim($this->media_path, '/') : null;
    }

    public function getMediaKindAttribute()
    {
        $mime = (string) ($this->media_mime ?? '');
        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        if (str_starts_with($mime, 'audio/')) return 'audio';
        return $mime !== '' ? 'document' : null;
    }

    public static function mediaFallbackLabel(?string $mime): string
    {
        return match (true) {
            str_starts_with((string) $mime, 'image/') => '[Gambar]',
            str_starts_with((string) $mime, 'video/') => '[Video]',
            str_starts_with((string) $mime, 'audio/') => '[Pesan suara]',
            default => '[Dokumen]',
        };
    }

    protected $casts = [
        'occurred_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /** Normalisasi nomor ke format 62xxxxxxxxxx untuk pencocokan. */
    public static function normalizePhone(?string $raw): string
    {
        $d = preg_replace('/\D/', '', (string) $raw);
        if (str_starts_with($d, '0')) {
            $d = '62' . substr($d, 1);
        } elseif (str_starts_with($d, '620')) {
            $d = '62' . substr($d, 3);
        }
        return $d;
    }

    /**
     * Kunci thread kanonis per (sesi, customer): kalau customer sudah punya
     * thread apa pun di sesi ini (mis. via LID), pakai kunci itu agar tidak
     * pecah jadi 2 percakapan untuk orang yang sama.
     * Return [phone, jid_server, customer_id].
     */
    public static function resolveThreadKey(string $sessionId, string $phone, string $server, ?int $customerId): array
    {
        if ($customerId) {
            $existing = static::where('session_id', $sessionId)
                ->where('customer_id', $customerId)
                ->latest('id')
                ->first(['phone', 'jid_server']);
            if ($existing) {
                return [$existing->phone, $existing->jid_server ?: 's.whatsapp.net', $customerId];
            }
        }
        return [$phone, $server, $customerId];
    }

    /** Varian format nomor customer di DB untuk dicocokkan. */
    public static function findCustomerId(string $normalizedPhone): ?int
    {
        $variants = [$normalizedPhone];
        if (str_starts_with($normalizedPhone, '62')) {
            $variants[] = '0' . substr($normalizedPhone, 2);
            $variants[] = '+' . $normalizedPhone;
        }
        return Customer::whereIn('phone', $variants)->value('id');
    }
}
