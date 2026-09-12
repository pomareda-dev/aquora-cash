<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\GoalContribution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoalContribution>
 */
class GoalContributionFactory extends Factory
{
    protected $model = GoalContribution::class;

    public function definition(): array
    {
        return [
            'goal_id' => Goal::factory(),
            'date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'amount' => fake()->randomFloat(2, 50, 500),
            'notes' => fake()->optional(0.5)->text(60),
            'is_sandbox' => false,
        ];
    }

    /**
     * Mark the contribution as sandbox.
     */
    public function sandboxed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sandbox' => true,
        ]);
    }
}
