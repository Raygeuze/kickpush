<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Comment extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $with = ['likes', 'dislikes'];

    protected $fillable = [
        'submission_id',
        'user_id',
        'content',
        'parent_id',
        'likes',
        'dislikes',
        'is_disapproved',
        'disapproved_by',
        'replying_to_id',
    ];

    // Auditing - For now I am not including likes and dislikes to
    // keep audit records small, may add them later if prove useful
    protected $auditInclude = [
        'content',
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Comment::class, 'parent_id')
            ->with('user', 'isReplyingTo', 'children')
            ->with('likes', 'dislikes')
            ->withCount('likes')
            ->withCount('dislikes');
    }

    public function disapprovedBy()
    {
        return $this->belongsTo(User::class, 'disapproved_by');
    }

    public function likes()
    {
        return $this->hasMany(CommentLike::class);
    }

    public function dislikes()
    {
        return $this->hasMany(CommentDislike::class);
    }

    public function isLikedByUser($userId)
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }

    public function isDislikedByUser($userId)
    {
        return $this->dislikes()->where('user_id', $userId)->exists();
    }

    public function isReplyingTo()
    {
        return $this->belongsTo(User::class, 'replying_to_id');
    }
}
