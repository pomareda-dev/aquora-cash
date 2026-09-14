<?php

use App\Models\Category;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\Movement;
use App\Models\RecurringTransaction;
use App\Models\Scopes\LiveScope;
use App\Models\User;
use App\Services\ProjectionService;
use App\Services\SandboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── CLI --user flag isolation ──────────────────────────────────

it('generate-projections --user does not create or delete sandbox rows', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    // Sandbox template + projections
    RecurringTransaction::factory()->for($user)->sandboxed()->create([
        'name' => 'Sandbox template',
        'amount' => '-300.00',
        'category_id' => $category->id,
        'day_of_month' => 15,
        'start_month' => now()->startOfMonth()->format('Y-m-d'),
        'active' => true,
    ]);

    $service = new ProjectionService;
    $service->generateSandboxForUser($user->id);

    $sandboxBefore = Movement::withoutSandboxScope()
        ->where('user_id', $user->id)
        ->where('is_sandbox', true)
        ->count();

    expect($sandboxBefore)->toBeGreaterThan(0);

    // Run CLI with --user flag
    $this->artisan('app:generate-projections', ['--user' => $user->id])
        ->assertExitCode(0);

    // Sandbox rows untouched
    $sandboxAfter = Movement::withoutSandboxScope()
        ->where('user_id', $user->id)
        ->where('is_sandbox', true)
        ->count();

    expect($sandboxAfter)->toBe($sandboxBefore);

    // No real projected movements were created from sandbox templates
    $realFromSandbox = Movement::where('user_id', $user->id)
        ->where('is_sandbox', false)
        ->where('recurring_id', '!=', null)
        ->where('source', 'recurring')
        ->count();

    expect($realFromSandbox)->toBe(0);
});

// ─── Real entity modifications while sandbox active ─────────────

it('modifying a real template does not affect sandbox rows', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    // Real template
    $realTemplate = RecurringTransaction::factory()->for($user)->for($category)->create([
        'name' => 'Real template',
        'amount' => '1000.00',
        'active' => true,
        'start_month' => now()->startOfMonth()->format('Y-m-d'),
    ]);

    // Sandbox movement
    $sandboxMovement = Movement::factory()->for($user)->sandboxed()->create([
        'description' => 'Sandbox mov',
        'amount' => '-200.00',
    ]);

    // Modify the real template
    $realTemplate->update(['amount' => '1500.00', 'name' => 'Updated real']);

    // Sandbox movement is untouched
    $sandbox = Movement::withoutSandboxScope()->find($sandboxMovement->id);
    expect($sandbox->is_sandbox)->toBeTrue()
        ->and($sandbox->description)->toBe('Sandbox mov')
        ->and($sandbox->amount)->toBe('-200.00');
});

it('deleting a real debt does not affect sandbox rows', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    // Real debt with a movement
    $realDebt = Debt::factory()->for($user)->create(['is_sandbox' => false]);
    Movement::factory()->for($user)->create([
        'debt_id' => $realDebt->id,
        'source' => 'manual',
        'amount' => '1000.00',
    ]);

    // Sandbox movement
    $sandboxMovement = Movement::factory()->for($user)->sandboxed()->create([
        'description' => 'Sandbox mov',
    ]);

    // Delete the real debt and its movements
    $realDebt->movements()->delete();
    $realDebt->delete();

    // Sandbox movement untouched
    expect(Movement::withoutSandboxScope()
        ->where('user_id', $user->id)
        ->where('is_sandbox', true)
        ->count())->toBe(1);

    // Real movements gone
    expect(Movement::where('user_id', $user->id)->count())->toBe(0);
});

it('modifying a real goal does not affect sandbox contributions', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create(['target_amount' => '2000.00']);

    // Sandbox contribution
    $sandboxContribution = GoalContribution::factory()->for($goal)->sandboxed()->create([
        'amount' => '300.00',
        'date' => now()->format('Y-m-d'),
    ]);

    // Modify the real goal
    $goal->update(['target_amount' => '5000.00', 'name' => 'Updated goal']);

    // Sandbox contribution untouched
    $contrib = GoalContribution::withoutSandboxScope()->find($sandboxContribution->id);
    expect($contrib->is_sandbox)->toBeTrue()
        ->and($contrib->amount)->toBe('300.00');
});

it('deleting a real goal with only sandbox contributions fails with FK protect', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create(['target_amount' => '1000.00']);

    // Only sandbox contributions — real query sees none (LiveScope)
    GoalContribution::factory()->for($goal)->sandboxed()->create([
        'amount' => '100.00',
        'date' => now()->format('Y-m-d'),
    ]);

    // The GoalController::destroy checks contributions()->withoutGlobalScope(LiveScope::class)->exists()
    // which sees sandbox contributions and prevents deletion.
    // Simulate the same check here:
    $hasContributions = $goal->contributions()->withoutGlobalScope(LiveScope::class)->exists();

    expect($hasContributions)->toBeTrue();
});

it('modifying sandbox entities does not affect real rows', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    // Real movement
    $realMovement = Movement::factory()->for($user)->create([
        'description' => 'Real mov',
        'amount' => '500.00',
    ]);

    // Sandbox movement
    $sandboxMovement = Movement::factory()->for($user)->sandboxed()->create([
        'description' => 'Sandbox mov',
        'amount' => '-100.00',
    ]);

    // Modify sandbox movement
    $sandboxMovement->update(['amount' => '-999.00', 'description' => 'Updated sandbox']);

    // Real movement untouched
    $real = Movement::find($realMovement->id);
    expect($real->description)->toBe('Real mov')
        ->and($real->amount)->toBe('500.00')
        ->and($real->is_sandbox)->toBeFalse();

    // Sandbox updated
    $sandbox = Movement::withoutSandboxScope()->find($sandboxMovement->id);
    expect($sandbox->amount)->toBe('-999.00')
        ->and($sandbox->description)->toBe('Updated sandbox');
});

// ─── Past-dated sandbox quota on commit ─────────────────────────

it('commit deletes past-dated sandbox projected movements and does not regenerate them', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    // Create a sandbox template that would generate a quota for a past date
    // We simulate this by creating a sandbox projected movement with a past date manually
    $pastDate = now()->subDays(5)->format('Y-m-d');

    $sandboxTemplate = RecurringTransaction::factory()->for($user)->for($category)->sandboxed()->create([
        'name' => 'Past quota template',
        'amount' => '-400.00',
        'category_id' => $category->id,
        'day_of_month' => now()->subDays(5)->day,
        'start_month' => now()->subMonth()->startOfMonth()->format('Y-m-d'),
        'active' => true,
    ]);

    // Manually create a past-dated sandbox projected movement
    // (simulating a quota whose date passed during the simulation)
    $pastQuota = Movement::forceCreate([
        'user_id' => $user->id,
        'date' => $pastDate,
        'description' => 'Past quota',
        'category_id' => $category->id,
        'amount' => '-400.00',
        'source' => 'recurring',
        'recurring_id' => $sandboxTemplate->id,
        'is_projected' => true,
        'is_sandbox' => true,
        'sort_order' => 1,
    ]);

    expect($pastQuota->is_sandbox)->toBeTrue()
        ->and($pastQuota->source)->toBe('recurring')
        ->and($pastQuota->is_projected)->toBeTrue();

    // Commit
    $this->actingAs($user)
        ->post(route('simulacion.guardar'))
        ->assertRedirect();

    // The past-dated sandbox projected movement was deleted (step 1 of commit)
    expect(Movement::withoutSandboxScope()->where('id', $pastQuota->id)->exists())->toBeFalse();

    // The template was promoted to real (step 3)
    $template = RecurringTransaction::withoutSandboxScope()->find($sandboxTemplate->id);
    expect($template->is_sandbox)->toBeFalse();

    // No real projected movement was created for the past date
    // (regenerateForUser only generates future movements)
    $realPastProjected = Movement::where('user_id', $user->id)
        ->where('date', $pastDate)
        ->where('source', 'recurring')
        ->where('is_projected', true)
        ->exists();

    expect($realPastProjected)->toBeFalse();
});

// ─── Cross-user isolation ───────────────────────────────────────

it('sandbox rows of one user are not affected by another users commit or revert', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $categoryA = Category::factory()->for($userA)->create();
    $categoryB = Category::factory()->for($userB)->create();

    // User A sandbox
    $sandboxA = Movement::factory()->for($userA)->sandboxed()->create([
        'description' => 'User A sandbox',
    ]);

    // User B sandbox
    $sandboxB = Movement::factory()->for($userB)->sandboxed()->create([
        'description' => 'User B sandbox',
    ]);

    // User A reverts
    $this->actingAs($userA)
        ->post(route('simulacion.revertir'))
        ->assertRedirect();

    // User A sandbox deleted
    expect(Movement::withoutSandboxScope()
        ->where('user_id', $userA->id)
        ->where('is_sandbox', true)
        ->count())->toBe(0);

    // User B sandbox untouched
    expect(Movement::withoutSandboxScope()
        ->where('user_id', $userB->id)
        ->where('is_sandbox', true)
        ->count())->toBe(1);

    $remainingB = Movement::withoutSandboxScope()
        ->where('user_id', $userB->id)
        ->where('is_sandbox', true)
        ->first();

    expect($remainingB->description)->toBe('User B sandbox');
});

// ─── Commit then revert idempotency ─────────────────────────────

it('revert after commit is a no-op', function () {
    $user = User::factory()->create();

    // Create sandbox movement and commit
    Movement::factory()->for($user)->sandboxed()->create([
        'source' => 'manual',
        'amount' => '-100.00',
    ]);

    $this->actingAs($user)
        ->post(route('simulacion.guardar'))
        ->assertRedirect();

    // No sandbox rows remain
    expect(Movement::withoutSandboxScope()
        ->where('user_id', $user->id)
        ->where('is_sandbox', true)
        ->count())->toBe(0);

    // Revert after commit = no-op (no error)
    $this->actingAs($user)
        ->post(route('simulacion.revertir'))
        ->assertRedirect();

    // Real movement still exists
    expect(Movement::where('user_id', $user->id)->count())->toBe(1);
});

// ─── Commit with mixed sandbox sources ──────────────────────────

it('commit handles a full scenario with all four entity types', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $user->update(['settings' => ['debt_category_id' => $category->id]]);
    $user->refresh();

    // 1. Sandbox manual movement
    Movement::factory()->for($user)->sandboxed()->create([
        'source' => 'manual',
        'amount' => '5000.00',
        'description' => 'Prestamo recibido',
    ]);

    // 2. Sandbox recurring template
    RecurringTransaction::factory()->for($user)->for($category)->sandboxed()->create([
        'name' => 'Cuota prestamo',
        'amount' => '-500.00',
        'day_of_month' => 15,
        'start_month' => now()->startOfMonth()->format('Y-m-d'),
        'active' => true,
    ]);

    // Generate sandbox projections
    $service = new ProjectionService;
    $service->generateSandboxForUser($user->id);

    // 3. Sandbox debt with movements
    $sandboxDebt = Debt::factory()->for($user)->sandboxed()->create([
        'principal_amount' => '2000.00',
        'installment_amount' => '400.00',
        'installments_count' => 5,
        'disbursement_date' => now()->format('Y-m-d'),
    ]);

    Movement::factory()->for($user)->sandboxed()->create([
        'date' => now()->format('Y-m-d'),
        'description' => 'Desembolso deuda',
        'category_id' => $category->id,
        'amount' => '2000.00',
        'source' => 'manual',
        'debt_id' => $sandboxDebt->id,
        'is_projected' => false,
    ]);

    // 4. Sandbox goal contributions (past and future)
    $goal = Goal::factory()->for($user)->create(['target_amount' => '3000.00']);
    GoalContribution::factory()->for($goal)->sandboxed()->create([
        'date' => now()->subDay()->format('Y-m-d'),
        'amount' => '500.00',
    ]);
    GoalContribution::factory()->for($goal)->sandboxed()->create([
        'date' => now()->addMonth()->format('Y-m-d'),
        'amount' => '300.00',
    ]);

    // Verify everything is sandbox
    expect(SandboxService::hasSandboxRows($user->id))->toBeTrue();

    // Commit
    $this->actingAs($user)
        ->post(route('simulacion.guardar'))
        ->assertRedirect();

    // No sandbox rows remain
    expect(SandboxService::hasSandboxRows($user->id))->toBeFalse();

    // Manual movement promoted
    expect(Movement::where('user_id', $user->id)
        ->where('description', 'Prestamo recibido')
        ->where('is_sandbox', false)
        ->exists())->toBeTrue();

    // Template promoted
    expect(RecurringTransaction::where('user_id', $user->id)
        ->where('name', 'Cuota prestamo')
        ->where('is_sandbox', false)
        ->exists())->toBeTrue();

    // Debt promoted
    expect(Debt::where('id', $sandboxDebt->id)
        ->where('is_sandbox', false)
        ->exists())->toBeTrue();

    // Debt movement promoted
    expect(Movement::where('debt_id', $sandboxDebt->id)
        ->where('is_sandbox', false)
        ->exists())->toBeTrue();

    // Past goal contribution promoted
    expect(GoalContribution::where('goal_id', $goal->id)
        ->where('is_sandbox', false)
        ->where('date', '<=', now()->toDateString())
        ->count())->toBe(1);

    // Future goal contribution deleted
    expect(GoalContribution::withoutSandboxScope()
        ->where('goal_id', $goal->id)
        ->where('date', '>', now()->toDateString())
        ->count())->toBe(0);

    // Real projected movements exist (from promoted template)
    expect(Movement::where('user_id', $user->id)
        ->where('source', 'recurring')
        ->where('is_projected', true)
        ->where('is_sandbox', false)
        ->count())->toBeGreaterThan(0);

    // hasSandbox is false on dashboard
    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('hasSandbox', false));
});
