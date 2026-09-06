<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimerSession extends Model
{
    use SoftDeletes;

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
        'started_at',
        'active_started_at',
        'paused_at',
        'stopped_at',
        'accumulated_seconds',
        'duration_seconds',
        'hourly_rate_snapshot',
        'hourly_rate_source',
        'currency_snapshot',
        'rate_snapshot_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'active_started_at' => 'datetime',
            'paused_at' => 'datetime',
            'stopped_at' => 'datetime',
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
