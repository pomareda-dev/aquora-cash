<?php

use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Store ─────────────────────────────────────────────────────

it('creates a sandbox contribution with past date', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create(['target_amount' => '5000.00']);

    $this->actingAs($user)
        ->post(route('simulacion.metas.aportes.store', $goal->id), [
            'amount' => '500.00',
            'date' => now()->subDay()->format('Y-m-d'),
            'notes' => 'Aporte simulado pasado',
        ])
        ->assertRedirect();

    $contribution = GoalContribution::withoutSandboxScope()
        ->where('goal_id', $goal->id)
        ->where('is_sandbox', true)
        ->first();

    expect($contribution)->not->toBeNull()
        ->and($contribution->amount)->toBe('500.00')
        ->and($contribution->is_sandbox)->toBeTrue();

    // Real query does NOT see it
    expect(GoalContribution::where('goal_id', $goal->id)->get())->toHaveCount(0);
});

it('creates a sandbox contribution with future date', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create(['target_amount' => '5000.00']);

    $this->actingAs($user)
        ->post(route('simulacion.metas.aportes.store', $goal->id), [
            'amount' => '500.00',
            'date' => now()->addMonth()->format('Y-m-d'),
        ])
        ->assertRedirect();

    $contribution = GoalContribution::withoutSandboxScope()
        ->where('goal_id', $goal->id)
        ->where('is_sandbox', true)
        ->first();

    expect($contribution)->not->toBeNull()
        ->and($contribution->date->isFuture())->toBeTrue();
});

// ─── Does not complete goal ────────────────────────────────────

it('sandbox contribution does not complete the goal', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create([
        'target_amount' => '500.00',
    ]);

    // Add enough sandbox contributions to exceed target
    $this->actingAs($user)
        ->post(route('simulacion.metas.aportes.store', $goal->id), [
            'amount' => '600.00',
            'date' => now()->format('Y-m-d'),
        ]);

    $goal->refresh();
    expect($goal->is_complete)->toBeFalse()
        ->and($goal->completed_at)->toBeNull();

    // progress_amount (real) should still be 0
    expect($goal->progress_amount)->toBe('0.00');
});

// ─── Destroy ───────────────────────────────────────────────────

it('deletes a sandbox contribution', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create();
    $contribution = GoalContribution::factory()->for($goal)->sandboxed()->create();

    $this->actingAs($user)
        ->delete(route('simulacion.metas.aportes.destroy', ['goal' => $goal->id, 'contribution' => $contribution->id]))
        ->assertRedirect();

    expect(GoalContribution::withoutSandboxScope()->where('id', $contribution->id)->exists())->toBeFalse();
});

// ─── Goal deletion blind spot fix ──────────────────────────────

it('cannot delete a goal with only sandbox contributions', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create();

    // Add only sandbox contributions
    GoalContribution::factory()->for($goal)->sandboxed()->create();

    $this->actingAs($user)
        ->delete(route('metas.destroy', $goal->id))
        ->assertStatus(409);

    // Goal still exists
    expect(Goal::where('id', $goal->id)->exists())->toBeTrue();
});

// ─── Index includes simulated_amount ───────────────────────────

it('goal index includes simulated_amount and apartado_simulado', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create(['target_amount' => '5000.00']);

    // Real contribution
    GoalContribution::factory()->for($goal)->create(['amount' => '1000.00']);

    // Sandbox contributions
    GoalContribution::factory()->for($goal)->sandboxed()->create(['amount' => '500.00']);
    GoalContribution::factory()->for($goal)->sandboxed()->create(['amount' => '300.00']);

    $response = $this->actingAs($user)->get(route('metas.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('goals.0.progress_amount', fn ($v) => (float) $v === 1000.0)
        ->where('goals.0.simulated_amount', fn ($v) => (float) $v === 800.0)
        ->where('summary.apartado', fn ($v) => (float) $v === 1000.0)
        ->where('summary.apartado_simulado', fn ($v) => (float) $v === 800.0)
    );
});

// ─── Isolation ─────────────────────────────────────────────────

it('sandbox endpoint returns 404 for real contribution id', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create();
    $realContribution = GoalContribution::factory()->for($goal)->create(['is_sandbox' => false]);

    $this->actingAs($user)
        ->delete(route('simulacion.metas.aportes.destroy', ['goal' => $goal->id, 'contribution' => $realContribution->id]))
        ->assertNotFound();
});

it('sandbox contribution does not affect real progress_amount', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create(['target_amount' => '5000.00']);

    GoalContribution::factory()->for($goal)->create(['amount' => '1000.00']);
    GoalContribution::factory()->for($goal)->sandboxed()->create(['amount' => '3000.00']);

    $goal->refresh();

    // progress_amount uses LiveScope (real only)
    expect($goal->progress_amount)->toBe('1000.00')
        ->and($goal->percent)->toBe(20);
});
