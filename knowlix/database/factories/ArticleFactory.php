<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'workspace_id' => Workspace::factory(),
            'category_id' => Category::factory(),
            'author_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(4),
            'body' => "## " . fake()->words(3, true) . "\n\n" . fake()->paragraph(),
            'status' => 'draft',
        ];
    }
}
