<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleVersion extends Model
{
    public $timestamps = false; // only has created_at, no updated_at

    protected $fillable = ['article_id', 'version', 'body', 'editor_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }
}
