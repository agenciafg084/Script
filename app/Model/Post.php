<?php

namespace App\Model;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    public const PENDING_STATUS = 0;
    public const APPROVED_STATUS = 1;
    public const DISAPPROVED_STATUS = 2;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'text',
        'price',
        'status',
        'release_date',
        'expire_date',
        'is_pinned',
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

    public function getIsExpiredAttribute() {
        if($this->expire_date > Carbon::now()){
            return false;
        }
        return true;
    }

    public function getIsScheduledAttribute() {
        if($this->release_date > Carbon::now()){
            return true;
        }
        return false;
    }

    /*
     * Relationships
     */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany('App\Model\PostComment');
    }

    public function reactions()
    {
        return $this->hasMany('App\Model\Reaction');
    }

    public function bookmarks()
    {
        return $this->hasMany('App\Model\UserBookmark');
    }

    public function attachments()
    {
        return $this->hasMany('App\Model\Attachment');
    }

    public function poll()
    {
        return $this->hasOne('App\Model\Poll', 'post_id', 'id');
    }

    public function transactions()
    {
        return $this->hasMany('App\Model\Transaction');
    }

    public function postPurchases()
    {
        return $this->hasMany('App\Model\Transaction', 'post_id', 'id')->where('status', 'approved')->where('type', 'post-unlock');
    }

    public function tips()
    {
        return $this->hasMany('App\Model\Transaction')->where('type', 'tip')->where('status', 'approved');
    }

    public static function getStatusName($status) {
        switch ($status){
            case self::PENDING_STATUS:
                return __("pending");
                break;
            case self::APPROVED_STATUS:
                return __("approved");
                break;
            case self::DISAPPROVED_STATUS:
                return __("disapproved");
                break;
        }
    }

    // Scopes
    public function scopeNotExpiredAndReleased($query) {
        $query->where(function ($query) {
            $query->where('release_date', '<', Carbon::now());
            $query->orWhere('release_date', null);
        });
        $query->where(function ($query) {
            $query->where('expire_date', '>', Carbon::now());
            $query->orWhere('expire_date', null);
        });
    }
}
