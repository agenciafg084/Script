<?php

namespace App\Model;

use App\Providers\AttachmentServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Stream extends Model
{
    public const PUSHR_DRIVER = 1;
    public const LIVEKIT_DRIVER = 2;

    /**
     * Streaming is currently playing.
     */
    public const IN_PROGRESS_STATUS = 'in-progress';

    /**
     * Streaming ended.
     */
    public const ENDED_STATUS = 'ended';

    /**
     * Stream deleted.
     */
    public const DELETED_STATUS = 'deleted';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'driver', 'user_id', 'status', 'name', 'slug', 'poster', 'pushr_id', 'hls_link', 'vod_link', 'rtmp_server', 'rtmp_key', 'price', 'requires_subscription', 'sent_expiring_reminder', 'is_public', 'settings',
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
        'ended_at' => 'datetime',
        'settings' => 'array',
    ];

    public function getPosterAttribute($value)
    {
        if($value){
            if(getSetting('storage.driver') == 's3'){
                return 'https://'.getSetting('storage.aws_bucket_name').'.s3.'.getSetting('storage.aws_region').'.amazonaws.com/'.$value;
            }
            elseif(getSetting('storage.driver') == 'wasabi' || getSetting('storage.driver') == 'do_spaces'){
                return Storage::url($value);
            }
            elseif(getSetting('storage.driver') == 'minio'){
                return rtrim(getSetting('storage.minio_endpoint'), '/').'/'.getSetting('storage.minio_bucket_name').'/'.$value;
            }
            elseif(getSetting('storage.driver') == 'pushr'){
                return rtrim(getSetting('storage.pushr_cdn_hostname'), '/').'/'.$value;
            }
            else{
                return Storage::disk('public')->url($value);
            }
        }else{
            return asset('/img/live-stream-cover.svg');
        }

    }

    /**
     * Relationships.
     */
    public function user()
    {
        return $this->belongsTo('App\Model\User', 'user_id');
    }

    public function messages()
    {
        return $this->hasMany('App\Model\StreamMessage');
    }

    public function streamPurchases()
    {
        return $this->hasMany('App\Model\Transaction', 'stream_id', 'id')->where('status', 'approved')->where('type', 'stream-access');
    }

    public function streamTips()
    {
        return $this->hasMany('App\Model\Transaction', 'stream_id', 'id')->where('status', 'approved')->where('type', 'tip');
    }

    public function isLivekitDriver() {
        return $this->driver === self::LIVEKIT_DRIVER;
    }

    public function getDriverSlug() {
        return $this->driver === self::LIVEKIT_DRIVER ? 'livekit' : 'pushr';
    }
}
