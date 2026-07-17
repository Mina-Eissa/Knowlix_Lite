<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $category): bool
    {
        return true;
    }

    public function update(User $user, Category $category): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Agent]);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->role === UserRole::Admin
            && $category->articles()->doesntExist();
    }
}
