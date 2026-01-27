<?php

namespace App\Model;

use App\Providers\GenericHelperServiceProvider;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use Notifiable;

    use HasRoles;

    use HasPanelShield; // Required for Shield to apply permissions in Filament

    public function canAccessPanel(Panel $panel): bool
    {
        if (config('settings.admin_version') !== 'v2') {
            return $this->role_id === 1;
        }

        // (v2) Allow all roles except "user"
        return $this->role_id !== 2;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'role_id', 'password', 'username', 'bio', 'birthdate', 'location', 'website', 'avatar', 'cover', 'settings',
        'billing_address', 'first_name', 'last_name', 'profile_access_price',
        'gender_id', 'gender_pronoun',
        'profile_access_price_6_months',
        'profile_access_price_12_months',
        'profile_access_price_3_months',
        'public_profile',
        'billing_address', 'first_name', 'last_name', 'city', 'country', 'state', 'postcode',
        'email_verified_at', 'paid_profile',
        'auth_provider', 'auth_provider_id', 'enable_2fa', 'enable_geoblocking', 'open_profile', 'referral_code',
        'last_active_at',
        'last_ip',
        'identity_verified_at',
        'stripe_account_id',
        'stripe_onboarding_verified',
        'stripe_onboarding_verified',
        'country_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'public_profile' => 'boolean',
        'settings' => 'array',
    ];

    /*
     * Virtual attributes
     * TODO: This causes some issues when we're trying to internally refer to to actual raw values
     * TODO: Maybe refactor
     */
    public function getAvatarAttribute($value)
    {
        return GenericHelperServiceProvider::getStorageAvatarPath($value);
    }

    public function getCoverAttribute($value)
    {
        return GenericHelperServiceProvider::getStorageCoverPath($value);
    }

    /**
     * Gets current count of active subscribers.
     * @return int
     * @throws \Exception
     */
    public function getFansCountAttribute() {
        $activeSubscriptionsCount = Subscription::query()
            ->where('recipient_user_id', Auth::user()->id)
            ->whereDate('expires_at', '>=', new \DateTime('now', new \DateTimeZone('UTC')))
            ->count('id');

        return $activeSubscriptionsCount;
    }

    /**
     * Gets the count of followers.
     * @return int|mixed
     */
    public function getFollowingCountAttribute() {
        $userId = Auth::user()->id;
        $userFollowingMembers = UserList::query()
            ->where(['user_id' => $userId, 'type' => 'following'])
            ->withCount('members')->first();

        return $userFollowingMembers != null && $userFollowingMembers->members_count > 0 ? $userFollowingMembers->members_count : 0;
    }

    public function getIsActiveCreatorAttribute($value)
    {
        if(getSetting('compliance.monthly_posts_before_inactive')){
            $check = Post::where('user_id', $this->id)->where('created_at', '>=', Carbon::now()->subdays(30))->count();
            $hasPassedPreApprovedLimit = true;
            if(getSetting('compliance.admin_approved_posts_limit')){
                $hasPassedPreApprovedLimit = Post::where('user_id', $this->id)->where('status', Post::APPROVED_STATUS)->count();
                $hasPassedPreApprovedLimit = $hasPassedPreApprovedLimit >= (int)getSetting('compliance.admin_approved_posts_limit');
            }
            return $hasPassedPreApprovedLimit && $check >= (int)getSetting('compliance.monthly_posts_before_inactive');
        }
        return true;
    }

    /*
     * Relationships
     */
    public function posts()
    {
            if(getSetting('compliance.admin_approved_posts_limit') > 0) {
                return $this->hasMany('App\Model\Post')->where('status', Post::APPROVED_STATUS);
            } else {
                return $this->hasMany('App\Model\Post');
            }
    }

    public function postComments()
    {
        return $this->hasMany('App\Model\PostComment');
    }

    public function reactions()
    {
        return $this->hasMany('App\Model\Reaction');
    }

    public function subscriptions()
    {
        return $this->hasMany('App\Model\Subscription');
    }

    public function activeSubscriptions()
    {
        return $this->hasMany('App\Model\Subscription', 'sender_user_id')->where('status', 'completed');
    }

    public function expiredSubscriptions($limit = 9)
    {
        return $this->hasMany('App\Model\Subscription', 'sender_user_id')->whereIn('status', [Subscription::EXPIRED_STATUS, Subscription::CANCELED_STATUS])->limit($limit);
    }

    public function activeCanceledSubscriptions()
    {
        return $this->hasMany('App\Model\Subscription', 'sender_user_id')->where('status', 'canceled')->where('expire_at', '<', Carbon::now());
    }

    public function subscribers()
    {
        return $this->hasMany('App\Model\Subscription', 'recipient_user_id');
    }

    public function transactions()
    {
        return $this->hasMany('App\Model\Transaction');
    }

    public function earningsTransactions()
    {
        return $this->hasMany(Transaction::class, 'recipient_user_id')
            ->where('status', Transaction::APPROVED_STATUS)
            ->where('type', '!=', Transaction::WITHDRAWAL_TYPE);
    }

    public function getEarningsYtdAttribute(): float
    {
        return (float) $this->earningsTransactions()
            ->whereYear('created_at', now()->year)
            ->sum('amount');
    }

    public function withdrawals()
    {
        return $this->hasMany('App\Model\Withdrawal');
    }

    public function attachments()
    {
        return $this->hasMany('App\Model\Attachment');
    }

    public function lists()
    {
        return $this->hasMany('App\Model\UserList');
    }

    public function bookmarks()
    {
        return $this->hasMany('App\Model\UserBookmark');
    }

    public function wallet()
    {
        return $this->hasOne('App\Model\Wallet');
    }

    public function verification()
    {
        return $this->hasOne('App\Model\UserVerify');
    }

    public function offer()
    {
        return $this->hasOne('App\Model\CreatorOffer');
    }

    public function userCountry()
    {
        return $this->belongsTo('App\Model\Country', 'country_id');
    }

    public function stories()
    {
        return $this->hasMany(Story::class)
            ->latest('created_at');
    }

    public function getLastActiveForHumansAttribute()
    {
        if (!$this->last_active_at) {
            return 'N/A';
        }

        $time = Carbon::parse($this->last_active_at);
        $secondsAgo = $time->diffInSeconds(now());

        // If it's less than 60 seconds, shift the timestamp so no seconds away are shown
        if ($secondsAgo < 60) {
            $time->subSeconds(60);
        }

        // This way, diffForHumans() will show "1 minute ago"
        return $time->diffForHumans();
    }
}
