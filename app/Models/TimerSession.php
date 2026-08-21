<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimerSession extends Model
{
    protected $fillable = [
        'user_id',
        'invoice_id',
        'task_id',
        'started_at',
        'paused_at',
        'stopped_at',
        'accumulated_seconds',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'stopped_at' => 'datetime',
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
}
