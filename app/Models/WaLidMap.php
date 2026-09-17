<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaLidMap extends Model
{
    protected $table = 'wa_lid_map';

    protected $fillable = ['session_id', 'lid', 'phone'];

    /** Simpan/refresh peta LID -> nomor. */
    public static function remember(string $sessionId, string $lid, string $phone): void
    {
        if ($lid === '' || $phone === '' || $lid === $phone) {
            return;
        }
        static::updateOrCreate(
            ['session_id' => $sessionId, 'lid' => $lid],
            ['phone' => $phone]
        );
    }

    /** Cari nomor asli dari LID, null bila belum pernah terpetakan. */
    public static function lookup(string $sessionId, string $lid): ?string
    {
        if ($lid === '') {
            return null;
        }
        return static::where('session_id', $sessionId)->where('lid', $lid)->value('phone');
    }
}
