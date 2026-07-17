<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'name'];

    public function articles()
    {
        return $this->morphedByMany(Article::class, 'taggable');
    }

    public function tickets()
    {
        return $this->morphedByMany(Ticket::class, 'taggable');
    }
}
