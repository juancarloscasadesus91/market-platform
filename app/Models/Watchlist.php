<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Watchlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol_id',
        'position',
        'notes',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function symbol(): BelongsTo
    {
        return $this->belongsTo(Symbol::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }
}
