<?php

namespace App\Model;

use App\Providers\AttachmentServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    public const PUBLIC_DRIVER = 0;
    public const S3_DRIVER = 1;
    public const WAS_DRIVER = 2;
    public const DO_DRIVER = 3;
    public const MINIO_DRIVER = 4;
    public const PUSHR_DRIVER = 5;
    public const R2_DRIVER = 6;

    // Disable auto incrementing as we set the id manually (uuid)
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'post_id', 'filename',
        'type', 'id', 'driver',
        'payment_request_id', 'message_id', 'coconut_id', 'story_id',
        'has_thumbnail', 'has_blurred_preview', 'length',
    ];

    protected $appends = ['attachmentType', 'path', 'thumbnail'];

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
        'id' => 'string',
    ];

    /*
     * Virtual attributes
     */

    public function getAttachmentTypeAttribute()
    {
        return AttachmentServiceProvider::getAttachmentType($this->type);
    }

    public function getPathAttribute()
    {
        return AttachmentServiceProvider::getFilePathByAttachment($this);
    }

    public function getThumbnailAttribute()
    {
        $path = 'posts/images/';
        if ($this->message_id) {
            $path = '/messenger/images/';
        }
        if($this->type == 'video'){
            $path = 'posts/videos'.'/thumbnails/'.$this->id.'.jpg';
        }
        return AttachmentServiceProvider::getThumbnailPathForAttachmentByResolution($this, 150, 150, $path);
    }

    // TODO: Add get blurredPreview
    public function getBlurredPreviewAttribute()
    {
        if(!$this->has_blurred_preview) return null;
        $path = 'posts/images/';
        if ($this->message_id) {
            $path = '/messenger/images/';
        }
        if($this->type == 'video'){
            $path = 'posts/videos'.'/thumbnails/'.$this->id.'.jpg';
        }
        return AttachmentServiceProvider::getBlurredPreviewPathForAttachment($this, $path);
    }

    /*
     * Relationships
     */

    public function user()
    {
        return $this->belongsTo('App\Model\User', 'user_id');
    }

    public function post()
    {
        return $this->belongsTo('App\Model\Post', 'post_id');
    }

    public function message()
    {
        return $this->belongsTo('App\Model\UserMessage', 'message_id');
    }

    public function paymentRequest()
    {
        return $this->belongsTo('App\Model\PaymentRequest', 'payment_request_id');
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function sound()
    {
        return $this->belongsTo(Sound::class, 'sound_id');
    }

    public static function getDriverName($driver): string {
        return match ($driver) {
            self::PUBLIC_DRIVER => "public",
            self::S3_DRIVER => "s3",
            self::WAS_DRIVER => "was",
            self::DO_DRIVER => "do",
            self::MINIO_DRIVER => "minio",
            self::PUSHR_DRIVER => "pushr",
            default => 'unknown',
        };
    }
}
