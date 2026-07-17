<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'requester_id' => User::factory(),
            'assignee_id' => null,
            'subject' => fake()->sentence(6),
            'body' => fake()->paragraph(),
            'status' => TicketStatus::Open,
            'priority' => fake()->randomElement(TicketPriority::cases()),
        ];
    }
}
