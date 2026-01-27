<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class PollAnswer extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'poll_id',
        'answer',
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

    /**
     * Get the poll that this answer belongs to.
     */
    public function poll()
    {
        return $this->belongsTo(Poll::class, 'poll_id');
    }

    /**
     * Get all user answers (choices) that point to this particular poll answer.
     */
    public function votes()
    {
        return $this->hasMany(PollUserAnswer::class, 'answer_id');
    }
}
