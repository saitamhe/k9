<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Position extends Model
{
    use HasFactory;

    protected $fillable = [
        'dog_id', 'seq', 'lat', 'lon', 'alt_m', 'speed_mps', 'heading_deg',
        'epoch_s', 'flags', 'rssi', 'snr', 'received_at',
    ];

    protected $casts = [
        'lat'         => 'float',
        'lon'         => 'float',
        'speed_mps'   => 'float',
        'snr'         => 'float',
        'received_at' => 'datetime',
    ];

    // Flags del firmware (debe coincidir con shared/protocol.h)
    public const FLAG_MOVING  = 0x01;
    public const FLAG_NO_FIX  = 0x02;
    public const FLAG_SOS     = 0x04;
    public const FLAG_LOW_BAT = 0x08;

    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class);
    }

    public function hasFix(): bool
    {
        return ($this->flags & self::FLAG_NO_FIX) === 0;
    }

    public function isMoving(): bool
    {
        return ($this->flags & self::FLAG_MOVING) !== 0;
    }
}
