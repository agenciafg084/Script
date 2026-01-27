<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class UserListMember extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'list_id',
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

    public function userList()
    {
        return $this->belongsTo(UserList::class, 'list_id', 'id');
    }

    public function userPosts()
    {
        return $this->user()->with(['posts']);
    }
}
