<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// ─── Cards with sandbox ────────────────────────────────────────────

it('dashboard cards include sandbox when include_sandbox=1', function () {
    $user = User::factory()->create();

    // Real movement
    Movement::factory()->for($user)->create([
        'amount' => '1000.00',
        'is_projected' => false,
        'date' => now()->format('Y-m-d'),
    ]);

    // Sandbox movement
    Movement::factory()->for($user)->sandboxed()->create([
        'amount' => '5000.00',
        'is_projected' => false,
        'date' => now()->format('Y-m-d'),
    ]);

    // Without sandbox
    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertInertia(fn ($page) => $page
        ->where('cards.realBalance', fn ($v) => (float) $v === 1000.0)
        ->where('includeSandbox', false)
    );

    // With sandbox
    $response = $this->actingAs($user)->get(route('dashboard', ['include_sandbox' => 1]));
    $response->assertInertia(fn ($page) => $page
        ->where('includeSandbox', true)
        ->where('simulatedCards.realBalance', fn ($v) => (float) $v === 6000.0)
    );
});

// ─── Chart data ────────────────────────────────────────────────────

it('dashboard provides chartDataSimulated when include_sandbox=1', function () {
    $user = User::factory()->create();

    Movement::factory()->for($user)->create([
        'amount' => '100.00',
        'is_projected' => false,
        'date' => now()->format('Y-m-d'),
    ]);

    Movement::factory()->for($user)->sandboxed()->create([
        'amount' => '500.00',
        'is_projected' => false,
        'date' => now()->format('Y-m-d'),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard', ['include_sandbox' => 1]));

    $response->assertInertia(fn ($page) => $page
        ->has('chartDataSimulated')
    );
});

// ─── Debts overview ────────────────────────────────────────────────

it('dashboard includes sandbox debts when include_sandbox=1', function () {
    $user = User::factory()->create();

    Debt::factory()->for($user)->create(['name' => 'Real debt']);
    Debt::factory()->for($user)->sandboxed()->create(['name' => 'Sandbox debt']);

    $response = $this->actingAs($user)->get(route('dashboard', ['include_sandbox' => 1]));

    $response->assertInertia(fn ($page) => $page
        ->where('debtsOverview', fn ($d) => count($d) === 1 && $d[0]['name'] === 'Real debt')
        ->where('simulatedDebtsOverview', fn ($d) => count($d) === 1 && $d[0]['name'] === 'Sandbox debt')
    );
});

// ─── Goals overview ────────────────────────────────────────────────

it('dashboard goals include simulated_amount when include_sandbox=1', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create(['target_amount' => '5000.00']);

    GoalContribution::factory()->for($goal)->create(['amount' => '1000.00']);
    GoalContribution::factory()->for($goal)->sandboxed()->create(['amount' => '500.00']);

    $response = $this->actingAs($user)->get(route('dashboard', ['include_sandbox' => 1]));

    $response->assertInertia(fn ($page) => $page
        ->where('simulatedGoalsOverview.0.simulated_amount', fn ($v) => (float) $v === 500.0)
    );
});

// ─── Without param is byte-compatible ──────────────────────────────

it('dashboard without include_sandbox is identical to before', function () {
    $user = User::factory()->create();

    Movement::factory()->for($user)->sandboxed()->create([
        'amount' => '5000.00',
        'is_projected' => false,
        'date' => now()->format('Y-m-d'),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('includeSandbox', false)
        ->where('simulatedCards', null)
        ->where('chartDataSimulated', null)
        ->where('simulatedDebtsOverview', null)
    );
});

// ─── Simulated cards: income and expense ───────────────────────────

it('simulated cards include sandbox income and expense', function () {
    $user = User::factory()->create();

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    Movement::factory()->for($user)->create([
        'amount' => '500.00',
        'is_projected' => false,
        'date' => '2026-07-05',
    ]);

    Movement::factory()->for($user)->create([
        'amount' => '-200.00',
        'is_projected' => false,
        'date' => '2026-07-10',
    ]);

    Movement::factory()->for($user)->sandboxed()->create([
        'amount' => '3000.00',
        'is_projected' => false,
        'date' => '2026-07-08',
    ]);

    Movement::factory()->for($user)->sandboxed()->create([
        'amount' => '-800.00',
        'is_projected' => false,
        'date' => '2026-07-12',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard', [
        'include_sandbox' => 1,
        'month' => '2026-07',
    ]));

    $response->assertInertia(fn ($page) => $page
        ->where('cards.monthIncome', fn ($v) => (float) $v === 500.0)
        ->where('cards.monthExpense', fn ($v) => (float) $v === 200.0)
        ->where('simulatedCards.monthIncome', fn ($v) => (float) $v === 3500.0)
        ->where('simulatedCards.monthExpense', fn ($v) => (float) $v === 1000.0)
    );

    Carbon::setTestNow();
});

// ─── Simulated chart data ──────────────────────────────────────────

it('chartDataSimulated accumulates real plus sandbox movements', function () {
    $user = User::factory()->create();

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    // Opening balance: 1000
    Movement::factory()->for($user)->create([
        'date' => '2026-06-30',
        'amount' => '1000.00',
        'is_projected' => false,
    ]);

    // Real: +500 on day 5
    Movement::factory()->for($user)->create([
        'date' => '2026-07-05',
        'amount' => '500.00',
        'is_projected' => false,
    ]);

    // Sandbox: +200 on day 5
    Movement::factory()->for($user)->sandboxed()->create([
        'date' => '2026-07-05',
        'amount' => '200.00',
        'is_projected' => false,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard', [
        'include_sandbox' => 1,
        'month' => '2026-07',
    ]));

    $response->assertInertia(fn ($page) => $page
        // Real chart: day 1 = 1000 (opening), day 5 = 1500
        ->where('chartData.0.balance', 1000)
        ->where('chartData.4.balance', 1500)
        // Simulated chart: day 5 = 1000 + 500 + 200 = 1700
        ->where('chartDataSimulated.4.balance', 1700)
    );

    Carbon::setTestNow();
});

// ─── Simulated budget overview ─────────────────────────────────────

it('simulated budget includes sandbox spending', function () {
    $user = User::factory()->create();

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    $food = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Comida',
        'kind' => 'expense',
        'monthly_limit' => 1000,
    ]);

    // Real spending: -200
    Movement::factory()->for($user)->create([
        'date' => '2026-07-05',
        'amount' => '-200.00',
        'category_id' => $food->id,
        'is_projected' => false,
    ]);

    // Sandbox spending: -300
    Movement::factory()->for($user)->sandboxed()->create([
        'date' => '2026-07-10',
        'amount' => '-300.00',
        'category_id' => $food->id,
        'is_projected' => false,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard', [
        'include_sandbox' => 1,
        'month' => '2026-07',
    ]));

    $response->assertInertia(fn ($page) => $page
        ->where('budgetOverview.0.spent', 200)
        ->where('simulatedBudgetOverview.0.spent', 500)
    );

    Carbon::setTestNow();
});

// ─── Simulated upcoming ────────────────────────────────────────────

it('simulated upcoming includes sandbox movements with is_sandbox flag', function () {
    $user = User::factory()->create();

    Carbon::setTestNow(Carbon::parse('2026-07-15'));

    Movement::factory()->for($user)->create([
        'date' => '2026-07-16',
        'description' => 'Real future',
        'amount' => '100.00',
        'is_projected' => false,
    ]);

    Movement::factory()->for($user)->sandboxed()->create([
        'date' => '2026-07-17',
        'description' => 'Sandbox future',
        'amount' => '200.00',
        'is_projected' => false,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard', ['include_sandbox' => 1]));

    $response->assertInertia(fn ($page) => $page
        ->where('upcomingProjections.0.description', 'Real future')
        ->has('simulatedUpcoming', 2)
        ->where('simulatedUpcoming.0.description', 'Real future')
        ->where('simulatedUpcoming.0.is_sandbox', false)
        ->where('simulatedUpcoming.1.description', 'Sandbox future')
        ->where('simulatedUpcoming.1.is_sandbox', true)
    );

    Carbon::setTestNow();
});

// ─── Difference with sandbox ───────────────────────────────────────

it('differenceWithSandbox is computed when include_sandbox=1', function () {
    $user = User::factory()->create();

    // Real balance: 1000
    Movement::factory()->for($user)->create([
        'amount' => '1000.00',
        'is_projected' => false,
        'date' => now()->format('Y-m-d'),
    ]);

    // Sandbox balance: +500
    Movement::factory()->for($user)->sandboxed()->create([
        'amount' => '500.00',
        'is_projected' => false,
        'date' => now()->format('Y-m-d'),
    ]);

    // Account balance: 1000 (matches real only)
    Account::factory()->for($user)->create([
        'balance' => 1000,
        'exclude_from_reconciliation' => false,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard', ['include_sandbox' => 1]));

    // differenceWithSandbox = 1000 (accounts) - 1500 (real + sandbox) = -500
    $response->assertInertia(fn ($page) => $page
        ->where('reconciliation.difference', 0)
        ->where('differenceWithSandbox', -500)
    );
});

// ─── Goals summary with sandbox ────────────────────────────────────

it('simulatedGoalsSummary includes apartado_with_sandbox', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create(['target_amount' => '5000.00']);

    GoalContribution::factory()->for($goal)->create(['amount' => '1000.00']);
    GoalContribution::factory()->for($goal)->sandboxed()->create(['amount' => '500.00']);

    $response = $this->actingAs($user)->get(route('dashboard', ['include_sandbox' => 1]));

    $response->assertInertia(fn ($page) => $page
        ->where('goalsSummary.apartado', fn ($v) => (float) $v === 1000.0)
        ->where('simulatedGoalsSummary.apartado', fn ($v) => (float) $v === 1000.0)
        ->where('simulatedGoalsSummary.apartado_with_sandbox', fn ($v) => (float) $v === 1500.0)
    );
});
