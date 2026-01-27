<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class UserTax extends Model
{
    public const DAC7_TYPE = 'dac7';
    public const ALLOWED_TAX_TYPES = [
        self::DAC7_TYPE,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'issuing_country_id', 'legal_name', 'tax_identification_number', 'vat_number', 'tax_type',
        'primary_address', 'date_of_birth',
        ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
    ];

    /*
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo('App\Model\User', 'user_id');
    }

    public function issuingCountry()
    {
        return $this->belongsTo('App\Model\Country', 'issuing_country_id');
    }
}
