<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123', // 'hashed' cast on the model hashes this automatically
            'role' => UserRole::Member,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function agent(): static
    {
        return $this->state(fn () => ['role' => UserRole::Agent]);
    }
}
