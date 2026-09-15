<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringTransaction>
 */
class RecurringTransactionFactory extends Factory
{
    protected $model = RecurringTransaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Sueldo', 'Alquiler', 'Seguro', 'Suscripción', 'Internet', 'Luz', 'Agua', 'Teléfono']),
            'category_id' => fn (array $attributes) => Category::factory()->create(['user_id' => $attributes['user_id']]),
            'amount' => fake()->randomElement([1200, 800, 1500, 500, 50, 80, 200, -200, -50]),
            'day_of_month' => fake()->numberBetween(1, 28),
            'start_month' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-01'),
            'end_month' => fake()->boolean(20) ? fake()->dateTimeBetween('+1 month', '+2 years')->format('Y-m-01') : null,
            'active' => true,
            'is_sandbox' => false,
        ];
    }

    /**
     * Mark the recurring transaction as sandbox.
     */
    public function sandboxed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sandbox' => true,
        ]);
    }
}
