<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'title' => fake()->unique()->sentence(4),
            'priority' => 'medium',
            'status' => 'pending',
            'source' => 'dashboard',
            'strategic_importance' => 3,
            'deadline' => now()->addDays(3),
        ];
    }
}
