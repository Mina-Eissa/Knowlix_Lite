<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Traits\BelongsToWorkspace;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, BelongsToWorkspace, HasFactory;

    protected $fillable = ['workspace_id', 'name', 'email', 'password', 'role'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function articlesAuthored()
    {
        return $this->hasMany(Article::class, 'author_id');
    }

    public function ticketsRequested()
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    public function ticketsAssigned()
    {
        return $this->hasMany(Ticket::class, 'assignee_id');
    }

    // Scope example: User::agents()->get()
    public function scopeAgents($query)
    {
        return $query->where('role', UserRole::Agent);
    }
}
