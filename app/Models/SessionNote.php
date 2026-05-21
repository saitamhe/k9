<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'search_session_id', 'user_id', 'body', 'lat', 'lon',
    ];

    protected $casts = [
        'lat' => 'float',
        'lon' => 'float',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(SearchSession::class, 'search_session_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
