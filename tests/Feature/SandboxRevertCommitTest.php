<?php

use App\Models\Category;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\Movement;
use App\Models\RecurringTransaction;
use App\Models\User;
use App\Services\ProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Revert ────────────────────────────────────────────────────

it('reverts all sandbox rows and leaves real state intact', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    // Real data
    $realMovement = Movement::factory()->for($user)->create(['is_sandbox' => false]);
    $realDebt = Debt::factory()->for($user)->create(['is_sandbox' => false]);
    $realGoal = Goal::factory()->for($user)->create(['target_amount' => '5000.00']);
    $realContribution = GoalContribution::factory()->for($realGoal)->create(['amount' => '500.00', 'is_sandbox' => false]);
    $realTemplate = RecurringTransaction::factory()->for($user)->for($category)->create(['is_sandbox' => false]);

    // Sandbox data
    $sandboxMovement = Movement::factory()->for($user)->sandboxed()->create();
    $sandboxTemplate = RecurringTransaction::factory()->for($user)->for($category)->sandboxed()->create([
        'active' => true,
        'start_month' => now()->startOfMonth()->format('Y-m-d'),
    ]);
    $sandboxDebt = Debt::factory()->for($user)->sandboxed()->create();
    $sandboxGoalContribution = GoalContribution::factory()->for($realGoal)->sandboxed()->create(['amount' => '200.00']);

    // Generate sandbox projections from the sandbox template
    $projectionService = new ProjectionService;
    $projectionService->generateSandboxForUser($user->id);

    // Verify sandbox rows exist
    expect(Movement::withoutSandboxScope()->where('user_id', $user->id)->where('is_sandbox', true)->count())->toBeGreaterThan(0);

    $this->actingAs($user)
        ->post(route('simulacion.revertir'))
        ->assertRedirect();

    // All sandbox rows deleted
    expect(Movement::withoutSandboxScope()->where('user_id', $user->id)->where('is_sandbox', true)->count())->toBe(0)
        ->and(RecurringTransaction::withoutSandboxScope()->where('user_id', $user->id)->where('is_sandbox', true)->count())->toBe(0)
        ->and(Debt::withoutSandboxScope()->where('user_id', $user->id)->where('is_sandbox', true)->count())->toBe(0)
        ->and(GoalContribution::withoutSandboxScope()->where('is_sandbox', true)->whereHas('goal', fn ($q) => $q->where('user_id', $user->id))->count())->toBe(0);

    // Real rows untouched
    expect(Movement::where('id', $realMovement->id)->exists())->toBeTrue()
        ->and(Debt::where('id', $realDebt->id)->exists())->toBeTrue()
        ->and(GoalContribution::where('id', $realContribution->id)->exists())->toBeTrue()
        ->and(RecurringTransaction::where('id', $realTemplate->id)->exists())->toBeTrue();

    // hasSandbox is now false
    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('hasSandbox', false));
});

it('revert is idempotent when no sandbox rows exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('simulacion.revertir'))
        ->assertRedirect();
});

// ─── Commit ────────────────────────────────────────────────────

it('promotes sandbox manual movements to real', function () {
    $user = User::factory()->create();
    $sandboxMovement = Movement::factory()->for($user)->sandboxed()->create([
        'source' => 'manual',
        'amount' => '-100.00',
    ]);

    $this->actingAs($user)
        ->post(route('simulacion.guardar'))
        ->assertRedirect();

    // Movement is now real
    $movement = Movement::where('id', $sandboxMovement->id)->first();
    expect($movement)->not->toBeNull()
        ->and($movement->is_sandbox)->toBeFalse();
});

it('promotes sandbox templates and regenerates real projections', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    $sandboxTemplate = RecurringTransaction::factory()->for($user)->for($category)->sandboxed()->create([
        'name' => 'Sueldo simulado',
        'amount' => '1500.00',
        'day_of_month' => 15,
        'start_month' => now()->startOfMonth()->format('Y-m-d'),
        'active' => true,
    ]);

    // Generate sandbox projections
    $projectionService = new ProjectionService;
    $projectionService->generateSandboxForUser($user->id);

    $this->actingAs($user)
        ->post(route('simulacion.guardar'))
        ->assertRedirect();

    // Template is now real
    $template = RecurringTransaction::where('id', $sandboxTemplate->id)->first();
    expect($template)->not->toBeNull()
        ->and($template->is_sandbox)->toBeFalse();

    // Real projected movements exist (regenerated from the now-real template)
    $realProjected = Movement::where('user_id', $user->id)
        ->where('source', 'recurring')
        ->where('is_projected', true)
        ->where('is_sandbox', false)
        ->count();

    expect($realProjected)->toBeGreaterThan(0);
});

it('promotes sandbox debt and its manual movements', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $user->update(['settings' => ['debt_category_id' => $category->id]]);
    $user->refresh();

    // Create sandbox debt with movements directly
    $sandboxDebt = Debt::factory()->for($user)->sandboxed()->create([
        'principal_amount' => '3000.00',
        'installment_amount' => '500.00',
        'installments_count' => 3,
        'disbursement_date' => now()->format('Y-m-d'),
    ]);

    // Disbursement movement (sandbox, manual)
    $disbursement = Movement::factory()->for($user)->sandboxed()->create([
        'date' => now()->format('Y-m-d'),
        'description' => "Desembolso {$sandboxDebt->name}",
        'category_id' => $category->id,
        'amount' => '3000.00',
        'source' => 'manual',
        'debt_id' => $sandboxDebt->id,
        'is_projected' => false,
    ]);

    // Installment movement (sandbox, manual)
    $installment = Movement::factory()->for($user)->sandboxed()->create([
        'date' => now()->addMonth()->format('Y-m-d'),
        'description' => "Cuota {$sandboxDebt->name} (1/3)",
        'category_id' => $category->id,
        'amount' => '-500.00',
        'source' => 'manual',
        'debt_id' => $sandboxDebt->id,
        'is_projected' => false,
    ]);

    $this->actingAs($user)
        ->post(route('simulacion.guardar'))
        ->assertRedirect();

    // Debt is now real
    $debt = Debt::where('id', $sandboxDebt->id)->first();
    expect($debt)->not->toBeNull()
        ->and($debt->is_sandbox)->toBeFalse();

    // Debt movements are now real
    $disbursementReal = Movement::where('id', $disbursement->id)->first();
    $installmentReal = Movement::where('id', $installment->id)->first();
    expect($disbursementReal->is_sandbox)->toBeFalse()
        ->and($installmentReal->is_sandbox)->toBeFalse();
});

it('promotes past goal contributions and discards future ones', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create(['target_amount' => '1000.00']);

    // Past sandbox contribution
    $pastContribution = GoalContribution::factory()->for($goal)->sandboxed()->create([
        'date' => now()->subDay()->format('Y-m-d'),
        'amount' => '600.00',
    ]);

    // Future sandbox contribution
    $futureContribution = GoalContribution::factory()->for($goal)->sandboxed()->create([
        'date' => now()->addMonth()->format('Y-m-d'),
        'amount' => '500.00',
    ]);

    $this->actingAs($user)
        ->post(route('simulacion.guardar'))
        ->assertRedirect();

    // Past contribution promoted to real
    $past = GoalContribution::where('id', $pastContribution->id)->first();
    expect($past)->not->toBeNull()
        ->and($past->is_sandbox)->toBeFalse();

    // Future contribution deleted
    expect(GoalContribution::withoutSandboxScope()->where('id', $futureContribution->id)->exists())->toBeFalse();

    // Goal progress reflects only the past contribution (600 / 1000 = 60%)
    $goal->refresh();
    expect($goal->progress_amount)->toBe('600.00')
        ->and($goal->percent)->toBe(60)
        ->and($goal->is_complete)->toBeFalse();
});

it('commit completes the goal when promoted contributions reach target', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create(['target_amount' => '500.00']);

    // Sandbox contribution that exceeds target
    GoalContribution::factory()->for($goal)->sandboxed()->create([
        'date' => now()->subDay()->format('Y-m-d'),
        'amount' => '600.00',
    ]);

    $this->actingAs($user)
        ->post(route('simulacion.guardar'))
        ->assertRedirect();

    $goal->refresh();
    expect($goal->is_complete)->toBeTrue()
        ->and($goal->completed_at)->not->toBeNull();
});

it('commit returns 422 when no sandbox rows exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('simulacion.guardar'))
        ->assertStatus(422);
});

it('commit is transactional and produces correct final state', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    // Mix of sandbox data
    Movement::factory()->for($user)->sandboxed()->create(['source' => 'manual', 'amount' => '-50.00']);
    RecurringTransaction::factory()->for($user)->for($category)->sandboxed()->create([
        'active' => true,
        'start_month' => now()->startOfMonth()->format('Y-m-d'),
    ]);

    // Generate sandbox projections
    $projectionService = new ProjectionService;
    $projectionService->generateSandboxForUser($user->id);

    $this->actingAs($user)
        ->post(route('simulacion.guardar'))
        ->assertRedirect();

    // No sandbox rows remain
    expect(Movement::withoutSandboxScope()->where('user_id', $user->id)->where('is_sandbox', true)->count())->toBe(0)
        ->and(RecurringTransaction::withoutSandboxScope()->where('user_id', $user->id)->where('is_sandbox', true)->count())->toBe(0);

    // hasSandbox is false
    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('hasSandbox', false));
});
