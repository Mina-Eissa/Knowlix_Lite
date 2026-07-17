<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $fillable = ['attachable_id', 'attachable_type', 'uploader_id', 'path', 'original_name', 'mime', 'size'];

    public function attachable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }
}
