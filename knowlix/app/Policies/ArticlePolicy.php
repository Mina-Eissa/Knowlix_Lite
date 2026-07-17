<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Article $article): bool
    {
        return true; // tenant scoping already ensures it's theirs
    }

    public function update(User $user, Article $article): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Agent]);
    }

    public function delete(User $user, Article $article): bool
    {
        return $user->role === UserRole::Admin;
    }
    public function submit(User $user, Article $article): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Agent]);
    }

    public function publish(User $user, Article $article): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Agent]);
    }
}
