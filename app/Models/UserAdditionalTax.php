<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAdditionalTax extends Model
{
    use HasFactory;

    protected $table = 'team_additional_taxes';

    protected $fillable = [
        'user_id',
        'team_id',
        'name',
        'category',
        'value_type',
        'value',
        'currency',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
