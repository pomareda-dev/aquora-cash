<?php

use App\Models\Category;
use App\Models\Movement;
use App\Models\RecurringTransaction;
use App\Models\User;
use App\Services\ProjectionService;

// ─── ProjectionService sandbox methods ─────────────────────────

it('generateSandboxForUser creates sandbox projected movements from sandbox templates', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    // Create a sandbox template
    RecurringTransaction::factory()->for($user)->sandboxed()->create([
        'name' => 'Cuota préstamo',
        'amount' => '-500.00',
        'category_id' => $category->id,
        'day_of_month' => 15,
        'start_month' => now()->startOfMonth()->format('Y-m-d'),
        'active' => true,
    ]);

    $service = new ProjectionService;
    $count = $service->generateSandboxForUser($user->id);

    expect($count)->toBeGreaterThan(0);

    // All generated movements are sandbox
    $movements = Movement::withoutSandboxScope()
        ->where('user_id', $user->id)
        ->where('is_sandbox', true)
        ->where('source', 'recurring')
        ->where('is_projected', true)
        ->get();

    expect($movements)->toHaveCount($count);

    // Real query sees nothing
    expect(Movement::where('user_id', $user->id)
        ->where('source', 'recurring')
        ->where('is_projected', true)
        ->get())->toHaveCount(0);
});

it('regenerateForUser does not touch sandbox rows', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    // Real template
    RecurringTransaction::factory()->for($user)->create([
        'name' => 'Real recurring',
        'amount' => '-100.00',
        'category_id' => $category->id,
        'day_of_month' => 15,
        'start_month' => now()->startOfMonth()->format('Y-m-d'),
        'active' => true,
    ]);

    // Sandbox template
    RecurringTransaction::factory()->for($user)->sandboxed()->create([
        'name' => 'Sandbox recurring',
        'amount' => '-500.00',
        'category_id' => $category->id,
        'day_of_month' => 20,
        'start_month' => now()->startOfMonth()->format('Y-m-d'),
        'active' => true,
    ]);

    $service = new ProjectionService;

    // Generate both
    $service->generateForUser($user->id);
    $service->generateSandboxForUser($user->id);

    $realCountBefore = Movement::where('user_id', $user->id)
        ->where('source', 'recurring')
        ->where('is_projected', true)
        ->count();

    $sandboxCountBefore = Movement::withoutSandboxScope()
        ->where('user_id', $user->id)
        ->where('is_sandbox', true)
        ->where('source', 'recurring')
        ->where('is_projected', true)
        ->count();

    // Regenerate real only
    $service->regenerateForUser($user->id);

    $realCountAfter = Movement::where('user_id', $user->id)
        ->where('source', 'recurring')
        ->where('is_projected', true)
        ->count();

    $sandboxCountAfter = Movement::withoutSandboxScope()
        ->where('user_id', $user->id)
        ->where('is_sandbox', true)
        ->where('source', 'recurring')
        ->where('is_projected', true)
        ->count();

    // Real was regenerated (same count), sandbox untouched
    expect($realCountAfter)->toBe($realCountBefore)
        ->and($sandboxCountAfter)->toBe($sandboxCountBefore);
});

it('regenerateSandboxForUser does not touch real rows', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    RecurringTransaction::factory()->for($user)->create([
        'name' => 'Real',
        'amount' => '-100.00',
        'category_id' => $category->id,
        'day_of_month' => 15,
        'start_month' => now()->startOfMonth()->format('Y-m-d'),
        'active' => true,
    ]);

    RecurringTransaction::factory()->for($user)->sandboxed()->create([
        'name' => 'Sandbox',
        'amount' => '-500.00',
        'category_id' => $category->id,
        'day_of_month' => 20,
        'start_month' => now()->startOfMonth()->format('Y-m-d'),
        'active' => true,
    ]);

    $service = new ProjectionService;
    $service->generateForUser($user->id);
    $service->generateSandboxForUser($user->id);

    $realBefore = Movement::where('user_id', $user->id)
        ->where('source', 'recurring')
        ->where('is_projected', true)
        ->count();

    $service->regenerateSandboxForUser($user->id);

    $realAfter = Movement::where('user_id', $user->id)
        ->where('source', 'recurring')
        ->where('is_projected', true)
        ->count();

    expect($realAfter)->toBe($realBefore);
});

// ─── SandboxRecurringController ────────────────────────────────

it('creates a sandbox recurring template via sandbox endpoint', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('simulacion.recurrentes.store'), [
            'name' => 'Cuota simulada',
            'amount' => '-500.00',
            'category_id' => $category->id,
            'day_of_month' => 15,
            'start_month' => now()->startOfMonth()->format('Y-m-d'),
            'active' => true,
        ])
        ->assertRedirect();

    $template = RecurringTransaction::withoutSandboxScope()
        ->where('user_id', $user->id)
        ->where('is_sandbox', true)
        ->first();

    expect($template)->not->toBeNull()
        ->and($template->name)->toBe('Cuota simulada');

    // Real query does NOT see it
    expect(RecurringTransaction::where('user_id', $user->id)->get())->toHaveCount(0);
});

it('deletes a sandbox template and its projected movements', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    $template = RecurringTransaction::factory()->for($user)->sandboxed()->create([
        'name' => 'Sandbox template',
        'amount' => '-500.00',
        'category_id' => $category->id,
        'day_of_month' => 15,
        'start_month' => now()->startOfMonth()->format('Y-m-d'),
        'active' => true,
    ]);

    // Generate sandbox projections
    $service = new ProjectionService;
    $service->generateSandboxForUser($user->id);

    $sandboxMovementsBefore = Movement::withoutSandboxScope()
        ->where('user_id', $user->id)
        ->where('is_sandbox', true)
        ->where('recurring_id', $template->id)
        ->count();

    expect($sandboxMovementsBefore)->toBeGreaterThan(0);

    $this->actingAs($user)
        ->delete(route('simulacion.recurrentes.destroy', $template->id))
        ->assertRedirect();

    // Template deleted
    expect(RecurringTransaction::withoutSandboxScope()
        ->where('id', $template->id)
        ->exists())->toBeFalse();

    // Its projected movements deleted
    expect(Movement::withoutSandboxScope()
        ->where('recurring_id', $template->id)
        ->exists())->toBeFalse();
});

it('sandbox endpoint returns 404 for real template id', function () {
    $user = User::factory()->create();
    $realTemplate = RecurringTransaction::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('simulacion.recurrentes.destroy', $realTemplate->id))
        ->assertNotFound();
});

it('real endpoint returns 404 for sandbox template id', function () {
    $user = User::factory()->create();
    $sandboxTemplate = RecurringTransaction::factory()->for($user)->sandboxed()->create();

    $this->actingAs($user)
        ->delete(route('recurrentes.destroy', $sandboxTemplate->id))
        ->assertNotFound();
});

// ─── CLI command isolation ─────────────────────────────────────

it('generate-projections command does not create or delete sandbox rows', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    // Sandbox template
    RecurringTransaction::factory()->for($user)->sandboxed()->create([
        'name' => 'Sandbox',
        'amount' => '-500.00',
        'category_id' => $category->id,
        'day_of_month' => 15,
        'start_month' => now()->startOfMonth()->format('Y-m-d'),
        'active' => true,
    ]);

    // Generate some sandbox projections first
    $service = new ProjectionService;
    $service->generateSandboxForUser($user->id);

    $sandboxBefore = Movement::withoutSandboxScope()
        ->where('user_id', $user->id)
        ->where('is_sandbox', true)
        ->count();

    // Run the CLI command (it calls regenerateForUser which is real-only)
    $this->artisan('app:generate-projections')
        ->assertExitCode(0);

    $sandboxAfter = Movement::withoutSandboxScope()
        ->where('user_id', $user->id)
        ->where('is_sandbox', true)
        ->count();

    expect($sandboxAfter)->toBe($sandboxBefore);
});
