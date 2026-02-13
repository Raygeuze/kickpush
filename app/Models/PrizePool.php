<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrizePool extends Model
{
    protected $fillable = [
        'total',
        'day_id',
    ];

    public function day()
    {
        return $this->belongsTo(Day::class);
    }
}
