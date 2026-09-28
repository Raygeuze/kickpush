<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkEntry extends Model
{
    use SoftDeletes;

    public const MODE_TIME = 'time';

    public const MODE_UNIT = 'unit';

    public const MODE_FIXED = 'fixed';

    public const MODES = [
        self::MODE_TIME,
        self::MODE_UNIT,
        self::MODE_FIXED,
    ];

    protected $fillable = [
        'user_id',
        'user_id_snapshot',
        'user_name_snapshot',
        'team_id',
        'invoice_id',
        'task_id',
        'task_id_snapshot',
        'task_name_snapshot',
        'project_id_snapshot',
        'project_name_snapshot',
        'client_id_snapshot',
        'client_name_snapshot',
        'billing_mode',
        'quantity',
        'unit_rate_snapshot',
        'unit_label_snapshot',
        'unit_label_plural_snapshot',
        'started_at',
        'active_started_at',
        'paused_at',
        'stopped_at',
        'accumulated_seconds',
        'duration_seconds',
        'notes',
        'hourly_rate_snapshot',
        'hourly_rate_source',
        'currency_snapshot',
        'rate_snapshot_at',
    ];

    protected $attributes = [
        'billing_mode' => self::MODE_TIME,
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'active_started_at' => 'datetime',
            'paused_at' => 'datetime',
            'stopped_at' => 'datetime',
            'quantity' => 'decimal:3',
            'unit_rate_snapshot' => 'decimal:2',
            'hourly_rate_snapshot' => 'decimal:2',
            'rate_snapshot_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isTimeBased(): bool
    {
        return ($this->billing_mode ?? self::MODE_TIME) === self::MODE_TIME;
    }

    public function isUnitBased(): bool
    {
        return $this->billing_mode === self::MODE_UNIT;
    }

    public function unitLabelFor(float $quantity): string
    {
        $singular = $this->unit_label_snapshot ?: 'unit';

        if (abs($quantity - 1.0) < 0.0001) {
            return $singular;
        }

        return $this->unit_label_plural_snapshot ?: $singular.'s';
    }

    public function isRunning(): bool
    {
        return $this->stopped_at === null && $this->paused_at === null;
    }

    public function isPaused(): bool
    {
        return $this->stopped_at === null && $this->paused_at !== null;
    }

    public function elapsedSeconds($at = null): int
    {
        if ($this->stopped_at !== null && $this->duration_seconds !== null) {
            return max(0, (int) $this->duration_seconds);
        }

        $accumulated = max(0, (int) ($this->accumulated_seconds ?? 0));

        if ($this->paused_at !== null) {
            return $accumulated;
        }

        $activeStartedAt = $this->active_started_at ?? $this->started_at;

        if (!$activeStartedAt) {
            return $accumulated;
        }

        $referenceTime = $at ?? $this->stopped_at ?? now();
        $activeSeconds = (int) floor($activeStartedAt->diffInSeconds($referenceTime));

        return $accumulated + max(0, $activeSeconds);
    }
}
