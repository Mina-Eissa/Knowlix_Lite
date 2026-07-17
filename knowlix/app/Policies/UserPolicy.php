<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // any authenticated user can list their own workspace's team
    }

    public function invite(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, User $target): bool
    {
        return $user->role === UserRole::Admin && $user->id !== $target->id;
    }
}
