<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'user_id',
        'team_id',
        'client_id',
        'conversion_source_currency',
        'conversion_target_currency',
        'conversion_rate',
        'conversion_rate_fetched_at',
        'financial_year_id',
        'invoice_number',
        'status',
        'issued_at',
        'due_at',
        'paid_at',
        'notes',
        'discount_type',
        'discount_value',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'conversion_rate' => 'decimal:8',
            'conversion_rate_fetched_at' => 'datetime',
            'discount_value' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function timerSessions(): HasMany
    {
        return $this->hasMany(TimerSession::class)
            ->orderByDesc('started_at');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class)
            ->orderByDesc('created_at');
    }
}
