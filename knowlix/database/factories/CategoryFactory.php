<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'workspace_id' => Workspace::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
