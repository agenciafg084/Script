<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class UserReport extends Model
{
    public const I_DONT_LIKE_TYPE = "I don't like this content";
    public const OFFENSIVE_CONTENT_TYPE = "Content is offensive or violates Terms of Service.";
    public const DMCA_TYPE = "Content contains stolen material (DMCA)";
    public const SPAM_TYPE = "Content is spam";
    public const ABUSE_TYPE = "Report abuse";
    public const RECEIVED_STATUS = 'received';
    public const SEEN_STATUS = 'seen';
    public const SOLVED_STATUS = 'solved';

    public static $typesMap = [
        self::I_DONT_LIKE_TYPE,
        self::OFFENSIVE_CONTENT_TYPE,
        self::DMCA_TYPE,
        self::SPAM_TYPE,
        self::ABUSE_TYPE,
    ];

    public static $statusMap = [
        self::RECEIVED_STATUS,
        self::SEEN_STATUS,
        self::SOLVED_STATUS,
        'false',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['from_user_id', 'user_id', 'post_id', 'message_id', 'stream_id', 'story_id', 'type', 'details', 'status'];

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

    public function reporterUser()
    {
        return $this->belongsTo('App\Model\User', 'from_user_id');
    }

    public function reportedUser()
    {
        return $this->belongsTo('App\Model\User', 'user_id');
    }

    public function reportedPost()
    {
        return $this->belongsTo('App\Model\Post', 'post_id');
    }

    public function reportedMessage()
    {
        return $this->belongsTo('App\Model\UserMessage', 'message_id');
    }

    public function reportedStream()
    {
        return $this->belongsTo('App\Model\Stream', 'stream_id');
    }

    public function reportedStory()
    {
        return $this->belongsTo(Story::class, 'story_id');
    }
}
