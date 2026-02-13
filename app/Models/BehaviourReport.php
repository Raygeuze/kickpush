<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BehaviourReport extends Model
{
    protected $fillable = [
        'reported_by',
        'reported_user_id',
        'reason',
        'details',
        'is_resolved',
        'resolved_by',
        'resolution_details',
    ];

    protected $appends = ['reportable_type'];

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function submission()
    {
        return $this->belongsTo(Submission::class, 'submission_id');
    }

    public function comment()
    {
        return $this->belongsTo(Comment::class, 'comment_id');
    }

    public function getReportableTypeAttribute()
    {
        if ($this->submission_id !== null) {
            return 'submission';
        } elseif ($this->comment_id !== null) {
            return 'comment';
        }
        return null;
    }
}
