<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Enums\AlertType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol_id',
        'alert_type',
        'condition',
        'threshold_value',
        'is_active',
        'is_triggered',
        'triggered_at',
        'metadata',
    ];

    protected $casts = [
        'alert_type' => AlertType::class,
        'threshold_value' => 'decimal:4',
        'is_active' => 'boolean',
        'is_triggered' => 'boolean',
        'triggered_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function symbol(): BelongsTo
    {
        return $this->belongsTo(Symbol::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTriggered($query)
    {
        return $query->where('is_triggered', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_triggered', false)->where('is_active', true);
    }
}
