<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'user_id',
        'team_id',
        'name',
        'email',
        'currency',
        'hourly_rate',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)
            ->orderBy('name');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)
            ->with('project:id,name,client_id')
            ->orderBy('name');
    }
}
