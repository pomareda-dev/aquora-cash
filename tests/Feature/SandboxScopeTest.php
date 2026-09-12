<?php

use App\Models\Debt;
use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\Movement;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;

// ─── LiveScope filtering ───────────────────────────────────────

it('excludes sandbox movements from default queries', function () {
    $user = User::factory()->create();
    Movement::factory()->for($user)->create(['is_sandbox' => false]);
    Movement::factory()->for($user)->sandboxed()->create();

    expect(Movement::where('user_id', $user->id)->get())->toHaveCount(1)
        ->and(Movement::where('user_id', $user->id)->first()->is_sandbox)->toBeFalse();
});

it('excludes sandbox recurring transactions from default queries', function () {
    $user = User::factory()->create();
    RecurringTransaction::factory()->for($user)->create(['is_sandbox' => false]);
    RecurringTransaction::factory()->for($user)->sandboxed()->create();

    expect(RecurringTransaction::where('user_id', $user->id)->get())->toHaveCount(1);
});

it('excludes sandbox debts from default queries', function () {
    $user = User::factory()->create();
    Debt::factory()->for($user)->create(['is_sandbox' => false]);
    Debt::factory()->for($user)->sandboxed()->create();

    expect(Debt::where('user_id', $user->id)->get())->toHaveCount(1);
});

it('excludes sandbox goal contributions from default queries', function () {
    $goal = Goal::factory()->create();
    GoalContribution::factory()->for($goal)->create(['is_sandbox' => false]);
    GoalContribution::factory()->for($goal)->sandboxed()->create();

    expect(GoalContribution::where('goal_id', $goal->id)->get())->toHaveCount(1);
});

// ─── withoutSandboxScope ───────────────────────────────────────

it('includes all rows with withoutSandboxScope', function () {
    $user = User::factory()->create();
    Movement::factory()->for($user)->create(['is_sandbox' => false]);
    Movement::factory()->for($user)->sandboxed()->create();

    expect(Movement::withoutSandboxScope()->where('user_id', $user->id)->get())->toHaveCount(2);
});

// ─── scopeSandbox ──────────────────────────────────────────────

it('retrieves only sandbox rows with sandbox scope', function () {
    $user = User::factory()->create();
    Movement::factory()->for($user)->create(['is_sandbox' => false]);
    Movement::factory()->for($user)->sandboxed()->create();

    $sandbox = Movement::sandbox()->where('user_id', $user->id)->get();
    expect($sandbox)->toHaveCount(1)
        ->and($sandbox->first()->is_sandbox)->toBeTrue();
});

// ─── Route model binding (404 for sandbox rows on real endpoints) ───

it('returns 404 when accessing a sandbox movement via real endpoint', function () {
    $user = User::factory()->create();
    $sandboxMovement = Movement::factory()->for($user)->sandboxed()->create();

    $this->actingAs($user)
        ->put(route('movimientos.update', $sandboxMovement), [])
        ->assertNotFound();
});

it('returns 404 when accessing a sandbox debt via real endpoint', function () {
    $user = User::factory()->create();
    $sandboxDebt = Debt::factory()->for($user)->sandboxed()->create();

    $this->actingAs($user)
        ->put(route('deudas.update', $sandboxDebt), [])
        ->assertNotFound();
});

// ─── Sandbox-aware relationships ───────────────────────────────

it('computes debt accessors correctly for sandbox debt with sandbox movements', function () {
    $user = User::factory()->create();
    $debt = Debt::factory()->for($user)->sandboxed()->create([
        'principal_amount' => '5000.00',
        'installment_amount' => '500.00',
        'installments_count' => 12,
    ]);

    // Create disbursement (positive, is_projected=false)
    Movement::factory()->for($user)->sandboxed()->create([
        'amount' => '5000.00',
        'is_projected' => false,
        'debt_id' => $debt->id,
        'source' => 'manual',
    ]);

    // Create 3 paid installments (negative, is_projected=false)
    for ($i = 0; $i < 3; $i++) {
        Movement::factory()->for($user)->sandboxed()->create([
            'amount' => '-500.00',
            'is_projected' => false,
            'debt_id' => $debt->id,
            'source' => 'manual',
        ]);
    }

    // Create 2 projected installments (negative, is_projected=true)
    for ($i = 0; $i < 2; $i++) {
        Movement::factory()->for($user)->sandboxed()->create([
            'amount' => '-500.00',
            'is_projected' => true,
            'debt_id' => $debt->id,
            'source' => 'manual',
        ]);
    }

    $debt->refresh();

    expect($debt->paid_installments)->toBe(3)
        ->and($debt->paid_amount)->toBe('1500.00')
        ->and($debt->remaining)->toBe('4500.00');
});

it('computes recurring transaction movements for sandbox template', function () {
    $user = User::factory()->create();
    $template = RecurringTransaction::factory()->for($user)->sandboxed()->create([
        'amount' => '-500.00',
    ]);

    Movement::factory()->for($user)->sandboxed()->create([
        'recurring_id' => $template->id,
        'amount' => '-500.00',
        'is_projected' => true,
    ]);

    $template->refresh();

    expect($template->movements)->toHaveCount(1);
});

// ─── Factory sandboxed state ───────────────────────────────────

it('creates sandbox movements via factory state', function () {
    $movement = Movement::factory()->sandboxed()->create();
    expect($movement->is_sandbox)->toBeTrue();
});

it('creates sandbox recurring transactions via factory state', function () {
    $rt = RecurringTransaction::factory()->sandboxed()->create();
    expect($rt->is_sandbox)->toBeTrue();
});

it('creates sandbox debts via factory state', function () {
    $debt = Debt::factory()->sandboxed()->create();
    expect($debt->is_sandbox)->toBeTrue();
});

it('creates sandbox goal contributions via factory state', function () {
    $contribution = GoalContribution::factory()->sandboxed()->create();
    expect($contribution->is_sandbox)->toBeTrue();
});

// ─── nextSandboxSortOrder ──────────────────────────────────────

it('computes next sandbox sort order independently from real', function () {
    $user = User::factory()->create();
    $date = '2026-09-12';

    // Real movement with sort_order 5
    Movement::factory()->for($user)->create([
        'date' => $date,
        'is_projected' => false,
        'sort_order' => 5,
    ]);

    // Sandbox movement with sort_order 2
    Movement::factory()->for($user)->sandboxed()->create([
        'date' => $date,
        'is_projected' => false,
        'sort_order' => 2,
    ]);

    expect(Movement::nextSandboxSortOrder($user->id, $date, false))->toBe(3)
        ->and(Movement::nextSortOrder($user->id, $date, false))->toBe(6);
});

// ─── Existing methods protected by LiveScope ───────────────────

it('realBalance excludes sandbox movements', function () {
    $user = User::factory()->create();

    Movement::factory()->for($user)->create([
        'amount' => '1000.00',
        'is_projected' => false,
        'date' => now()->toDateString(),
    ]);

    Movement::factory()->for($user)->sandboxed()->create([
        'amount' => '5000.00',
        'is_projected' => false,
        'date' => now()->toDateString(),
    ]);

    expect(Movement::realBalance($user->id))->toBe('1000.00');
});

it('openingBalance excludes sandbox movements', function () {
    $user = User::factory()->create();
    $monthStart = Carbon::parse(now()->startOfMonth());

    Movement::factory()->for($user)->create([
        'amount' => '500.00',
        'is_projected' => false,
        'date' => now()->subMonth()->toDateString(),
    ]);

    Movement::factory()->for($user)->sandboxed()->create([
        'amount' => '3000.00',
        'is_projected' => false,
        'date' => now()->subMonth()->toDateString(),
    ]);

    expect(Movement::openingBalance($monthStart, $user->id))->toBe('500.00');
});
