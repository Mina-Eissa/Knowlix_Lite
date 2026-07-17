<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Workspace extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'slug', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function tags()
    {
        return $this->hasMany(Tag::class);
    }
}
