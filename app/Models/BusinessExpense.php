<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessExpense extends Model
{
    protected $fillable = [
        'user_id',
        'team_id',
        'financial_year_id',
        'name',
        'description',
        'amount',
        'incurred_on',
        'tax_deductible',
        'deductible_percentage',
        'receipt_path',
        'receipt_original_name',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'incurred_on' => 'date',
            'tax_deductible' => 'boolean',
            'deductible_percentage' => 'decimal:2',
        ];
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }
}
