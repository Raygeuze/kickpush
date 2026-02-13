<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoteCount extends Model
{
    protected $fillable = [
        'date',
        'count',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function day()
    {
        return $this->belongsTo(Day::class, 'day_id');
    }

    public function submissions()
    {
        return $this->belongsToMany(Submission::class);
    }
}
