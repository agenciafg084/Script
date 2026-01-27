<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRequest extends Model
{
    public const PENDING_STATUS = 'pending';
    public const APPROVED_STATUS = 'approved';
    public const REJECTED_STATUS = 'rejected';
    public const DEPOSIT_TYPE = 'deposit';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'transaction_id', 'status', 'type', 'reason', 'message', 'amount',
    ];

    protected $appends = ['files'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Get request files path as array.
     */
    public function getFilesAttribute() {
        $files = [];
        $attachments = $this->attachments;
        if($attachments && count($attachments)){
            foreach ($attachments as $attachment){
                $files[] = $attachment['filename'];
            }
        }
        return $files;
    }

    public function attachments()
    {
        return $this->hasMany('App\Model\Attachment');
    }

    public function user(): BelongsTo
    {
        // belongsTo(Related::class, foreignKey_on_this_model, ownerKey_on_related)
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id', 'id');
    }
}
