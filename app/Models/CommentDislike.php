<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentDislike extends Model
{
    protected $fillable = [
        'comment_id',
        'user_id',
    ];

    public function comment()
    {
        return $this->belongsTo(Comments::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
