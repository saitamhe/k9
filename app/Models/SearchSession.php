<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SearchSession extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'uuid', 'name', 'started_at', 'ended_at',
        'base_lat', 'base_lon', 'base_name',
        'description', 'status', 'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $session) {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        // Permite seguir resolviendo /sessions/{session} por id en rutas web,
        // pero las rutas API usan ->where('uuid', ...) explicitamente.
        return 'id';
    }

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
        'base_lat'   => 'float',
        'base_lon'   => 'float',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function waypoints(): HasMany
    {
        return $this->hasMany(Waypoint::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(SessionNote::class)->orderBy('created_at');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Devuelve la sesion activa (a lo sumo una). NULL si no hay operativo en curso.
     */
    public static function current(): ?self
    {
        return self::active()->latest('started_at')->first();
    }
}
