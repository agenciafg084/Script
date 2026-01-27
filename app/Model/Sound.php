<?php

namespace App\Model;

use App\Providers\AttachmentServiceProvider;
use Illuminate\Database\Eloquent\Model;

class Sound extends Model
{
    protected $fillable = [
        'title',
        'artist',
        'description',
        'is_active',
    ];

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'sound_id');
    }

    public function audioAttachment()
    {
        return $this->hasOne(Attachment::class, 'sound_id')
            ->whereIn('type', AttachmentServiceProvider::getTypeByExtension('audio'));
    }

    public function coverAttachment()
    {
        return $this->hasOne(Attachment::class, 'sound_id')
            ->whereIn('type', AttachmentServiceProvider::getTypeByExtension('default'));
    }
}
