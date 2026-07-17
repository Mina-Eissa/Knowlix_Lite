<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Article extends Model
{
    use BelongsToWorkspace, SoftDeletes,HasFactory;

    protected $fillable = ['workspace_id', 'category_id', 'author_id', 'title', 'slug', 'body','status','published_at'];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function versions()
    {
        return $this->hasMany(ArticleVersion::class);
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // Scope example: Article::published()->get()
    public function scopePublished($query)
    {
        return $query->where('status', ArticleStatus::Published);
    }

    public function isPublishable(): bool
    {
        return $this->status === ArticleStatus::InReview;
    }

    public function isEditable(): bool
    {
        return $this->status === ArticleStatus::Draft || $this->status === ArticleStatus::InReview;
    }
}
