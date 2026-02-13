<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Day extends Model
{
    protected $fillable = [
        'topic',
        'description',
        'first_place_user_id',
        'second_place_user_id',
        'third_place_user_id',
    ];

    protected $appends = [
        'is_today',
    ];

    public function firstPlaceUser()
    {
        return $this->belongsTo(User::class, 'first_place_user_id');
    }

    public function secondPlaceUser()
    {
        return $this->belongsTo(User::class, 'second_place_user_id');
    }

    public function thirdPlaceUser()
    {
        return $this->belongsTo(User::class, 'third_place_user_id');
    }

    public function submissions()
    {
        if(env('FORCE_SUBMISSION_APPROVAL') == true) {
            return $this->hasMany(Submission::class)
                ->where('is_approved', true)
                ->where('is_disapproved', false)
                ->with('user')
                ->orderByDesc('votes');
        }
        else {
            return $this->hasMany(Submission::class)
                ->with('user')
                ->orderByDesc('votes');
        }
    }

    // public function approvedSubmissions()
    // {
    //     return $this->hasMany(Submission::class)->where('is_approved', true);
    // }

    public function unapprovedSubmissions()
    {
        return $this->hasMany(Submission::class)->where('is_approved', false);
    }

    public function disapprovedSubmissions()
    {
        if(env('FORCE_SUBMISSION_APPROVAL') == true) {
            return $this->hasMany(Submission::class)->where('is_approved', false)->where('is_disapproved', true);
        }
        else {
            return [];
        }
    }

    public function unprocessedSubmissions()
    {
        return $this->hasMany(Submission::class)->where('approved_by', null)->where('disapproved_by', null);
    }

    public function prizePool()
    {
        return $this->hasOne(PrizePool::class);
    }

    public function comments()
    {
        return $this->hasManyThrough(
            \App\Models\Comment::class,
            \App\Models\Submission::class,
            'day_id',      // Foreign key on submissions table
            'submission_id', // Foreign key on comments table
            'id',          // Local key on days table
            'id'           // Local key on submissions table
        );
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    public function getIsTodayAttribute()
    {
        $latestDay = Day::latest()->first();

        return $latestDay->id === $this->id;
    }
}
