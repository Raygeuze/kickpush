<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Day;
use App\Models\Comment;
use OwenIt\Auditing\Contracts\Auditable;

class Submission extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'title',
        'description',
        'user_id',
        'votes',
        'downvotes',
        'views',
        'day_id',
        'is_approved',
        'embed_link'
    ];

    // Auditing - For now I am not including votes, downvotes and views to
    // keep audit records small, may add them later if prove useful
    protected $auditInclude = [
        'title',
        'description',
        'embed_link'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function day()
    {
        return $this->belongsTo(Day::class);
    }

    public function approve()
    {
        $this->is_approved = true;
        $this->approved_by = Auth::guard('admin')->id();
        $this->save();
    }

    public function disapprove($reason = null)
    {
        $this->is_approved = false;
        $this->is_disapproved = true;
        $this->disapproved_by = Auth::guard('admin')->id();
        $this->disapproval_reason = $reason;
        $this->save();
    }

    //both parent level and replies
    public function comments()
    {
        return $this->hasMany(Comment::class)
            ->with('children', 'user')
            ->with('likes', 'dislikes')
            ->withCount('likes')
            ->withCount('dislikes')
            ->orderBy('likes_count', 'desc');
    }

    public function parentComments()
    {
        return $this->comments()->whereNull('parent_id')
            ->with('children', 'user')
            ->with('likes', 'dislikes')
            ->withCount('likes')
            ->withCount('dislikes')
            ->orderBy('likes_count', 'desc');
    }
}
