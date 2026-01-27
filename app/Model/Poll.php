<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Poll extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'post_id', 'ends_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [

    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [

    ];

    /*
     * Relationships
     */

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }

    /**
     * Get the possible answers for this poll.
     */
    public function answers()
    {
        return $this->hasMany(PollAnswer::class, 'poll_id');
    }

    /**
     * Get all user answers related to this poll.
     */
    public function userAnswers()
    {
        return $this->hasMany(PollUserAnswer::class, 'poll_id');
    }
}
