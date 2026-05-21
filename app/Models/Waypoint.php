<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Waypoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'search_session_id', 'session_id', 'type',
        'lat', 'lon', 'note', 'photo_path', 'recorded_at',
    ];

    protected $casts = [
        'lat'         => 'float',
        'lon'         => 'float',
        'recorded_at' => 'datetime',
    ];

    public const TYPES = [
        'article',        // articulo encontrado (prenda, huella, pista)
        'k9_alert',       // perro marco positivo
        'k9_interest',    // perro mostro interes sin marcar
        'contamination',  // zona contaminada (otros rescatistas, animales)
        'rest',           // descanso / hidratacion del binomio
        'other',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(SearchSession::class, 'search_session_id');
    }
}
