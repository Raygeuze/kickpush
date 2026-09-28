<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'team_id',
        'client_id',
        'project_id',
        'name',
        'description',
        'billing_mode',
        'unit_label',
        'unit_label_plural',
        'unit_rate',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'unit_rate' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workEntries(): HasMany
    {
        return $this->hasMany(WorkEntry::class)
            ->latest('started_at');
    }

    public function resolvedBillingMode(): string
    {
        $mode = $this->billing_mode ?: optional($this->resolvedProject())->billing_mode;

        return in_array($mode, WorkEntry::MODES, true) ? $mode : WorkEntry::MODE_TIME;
    }

    public function resolvedUnitLabel(): ?string
    {
        $label = $this->unit_label ?: optional($this->resolvedProject())->unit_label;

        return $label ? (string) $label : null;
    }

    public function resolvedUnitLabelPlural(): ?string
    {
        $plural = $this->unit_label_plural ?: optional($this->resolvedProject())->unit_label_plural;

        if ($plural) {
            return (string) $plural;
        }

        $singular = $this->resolvedUnitLabel();

        return $singular ? $singular.'s' : null;
    }

    public function resolvedUnitRate(): ?float
    {
        $rate = $this->unit_rate ?? optional($this->resolvedProject())->unit_rate;

        return $rate === null ? null : (float) $rate;
    }

    /**
     * Resolved billing settings after project inheritance, keyed for API payloads.
     */
    public function billingConfig(): array
    {
        $mode = $this->resolvedBillingMode();

        return [
            'billing_mode' => $mode,
            'unit_label' => $this->resolvedUnitLabel(),
            'unit_label_plural' => $this->resolvedUnitLabelPlural(),
            'unit_rate' => $this->resolvedUnitRate(),
            'is_inherited' => $this->billing_mode === null,
            'is_configured' => $mode !== WorkEntry::MODE_UNIT
                || ($this->resolvedUnitLabel() !== null && $this->resolvedUnitRate() !== null),
        ];
    }

    private function resolvedProject(): ?Project
    {
        if (!$this->project_id) {
            return null;
        }

        $this->loadMissing('project');

        return $this->project;
    }
}
