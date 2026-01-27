<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    public const REQUESTED_STATUS = 'requested';
    public const REJECTED_STATUS = 'rejected';
    public const APPROVED_STATUS = 'approved';
    public const STRIPE_CONNECT_METHOD = 'Stripe Connect';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'status',
        'amount',
        'fee',
        'message',
        'payment_method',
        'payment_identifier',
        'stripe_payout_id',
        'stripe_transfer_id',
        'processed',
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
        'amount' => 'decimal:2',
        'fee'    => 'decimal:2',
    ];
    /*
     * Relationships
     */

    public function user()
    {
        return $this->belongsTo('App\Model\User', 'user_id');
    }

    public function getNetAmountAttribute(): float
    {
        return (float) $this->amount - (float) $this->fee;
    }

    public static function calculateFee(float $amount): float
    {
        if (!getSetting('payments.withdrawal_allow_fees')) {
            return 0.0;
        }

        $percentage = (float) getSetting('payments.withdrawal_default_fee_percentage');

        if ($percentage <= 0) {
            return 0.0;
        }

        return $amount * ($percentage / 100);
    }
}
