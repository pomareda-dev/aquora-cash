<?php

namespace Database\Factories;

use App\Models\Debt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Debt>
 */
class DebtFactory extends Factory
{
    protected $model = Debt::class;

    public function definition(): array
    {
        $installmentsCount = fake()->numberBetween(3, 12);
        $disbursementDate = fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d');

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement([
                'Préstamo personal',
                'Préstamo auto',
                'Préstamo moto',
                'Crédito educativo',
            ]),
            'principal_amount' => fake()->randomFloat(2, 1000, 20000),
            'disbursement_date' => $disbursementDate,
            'installment_amount' => fn (array $attributes) => fake()->randomFloat(2, 100, 2000),
            'installments_count' => $installmentsCount,
            'payment_dates' => fn (array $attributes) => collect(range(0, $attributes['installments_count'] - 1))
                ->map(fn (int $i) => Carbon::parse($attributes['disbursement_date'])
                    ->addMonths($i + 1)
                    ->format('Y-m-d'))
                ->all(),
            'closed_at' => null,
            'is_sandbox' => false,
        ];
    }

    /**
     * Mark the debt as sandbox.
     */
    public function sandboxed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sandbox' => true,
        ]);
    }

    /**
     * Mark the debt as closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'closed_at' => now(),
        ]);
    }
}
