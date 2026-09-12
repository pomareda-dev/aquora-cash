<?php

use App\Models\Category;
use App\Models\Movement;
use App\Models\User;

// ─── Store ─────────────────────────────────────────────────────

it('creates a sandbox movement via sandbox endpoint', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('simulacion.movimientos.store'), [
            'date' => now()->format('Y-m-d'),
            'description' => 'Préstamo simulado',
            'category_id' => $category->id,
            'amount' => '5000.00',
            'is_projected' => false,
        ])
        ->assertRedirect();

    // Sandbox movement exists
    $movement = Movement::withoutSandboxScope()
        ->where('user_id', $user->id)
        ->where('is_sandbox', true)
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->description)->toBe('Préstamo simulado')
        ->and($movement->source)->toBe('manual')
        ->and($movement->is_sandbox)->toBeTrue();

    // Real query does NOT see it
    expect(Movement::where('user_id', $user->id)->get())->toHaveCount(0);
});

it('sandbox movement does not appear in real movement index', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    // Create a real movement
    Movement::factory()->for($user)->create([
        'description' => 'Real movement',
        'category_id' => $category->id,
        'date' => now()->format('Y-m-d'),
    ]);

    // Create a sandbox movement
    Movement::factory()->for($user)->sandboxed()->create([
        'description' => 'Sandbox movement',
        'category_id' => $category->id,
        'date' => now()->format('Y-m-d'),
    ]);

    $response = $this->actingAs($user)->get(route('movimientos.index'));

    // The real movements list should only have 1
    $realMovementId = Movement::where('user_id', $user->id)->first()->id;
    $response->assertInertia(fn ($page) => $page
        ->has('realMovements', 1)
        ->where('realMovements', fn ($movements) => $movements[0]['description'] === 'Real movement' && $movements[0]['id'] === $realMovementId)
    );
});

// ─── Update ────────────────────────────────────────────────────

it('updates a sandbox movement via sandbox endpoint', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $movement = Movement::factory()->for($user)->sandboxed()->create([
        'description' => 'Original',
        'category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->put(route('simulacion.movimientos.update', $movement->id), [
            'date' => $movement->date->format('Y-m-d'),
            'description' => 'Updated sandbox',
            'category_id' => $category->id,
            'amount' => '100.00',
            'is_projected' => false,
        ])
        ->assertRedirect();

    $movement->refresh();
    expect($movement->description)->toBe('Updated sandbox');
});

// ─── Destroy ───────────────────────────────────────────────────

it('deletes a sandbox movement via sandbox endpoint', function () {
    $user = User::factory()->create();
    $movement = Movement::factory()->for($user)->sandboxed()->create();

    $this->actingAs($user)
        ->delete(route('simulacion.movimientos.destroy', $movement->id))
        ->assertRedirect();

    expect(Movement::withoutSandboxScope()->where('id', $movement->id)->exists())->toBeFalse();
});

// ─── Isolation ─────────────────────────────────────────────────

it('sandbox endpoint cannot modify real movements', function () {
    $user = User::factory()->create();
    $realMovement = Movement::factory()->for($user)->create(['is_sandbox' => false]);

    $this->actingAs($user)
        ->put(route('simulacion.movimientos.update', $realMovement->id), [
            'date' => now()->format('Y-m-d'),
            'description' => 'Hacked',
            'amount' => '100.00',
        ])
        ->assertNotFound();

    $realMovement->refresh();
    expect($realMovement->description)->not->toBe('Hacked');
});

it('real endpoint cannot modify sandbox movements', function () {
    $user = User::factory()->create();
    $sandboxMovement = Movement::factory()->for($user)->sandboxed()->create();

    $this->actingAs($user)
        ->put(route('movimientos.update', $sandboxMovement->id), [
            'date' => now()->format('Y-m-d'),
            'description' => 'Hacked',
            'amount' => '100.00',
        ])
        ->assertNotFound();
});

it('sandbox endpoint returns 403 for another user sandbox movement', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $sandboxMovement = Movement::factory()->for($otherUser)->sandboxed()->create();

    $this->actingAs($user)
        ->delete(route('simulacion.movimientos.destroy', $sandboxMovement->id))
        ->assertForbidden();
});

// ─── Balance isolation ────────────────────────────────────────

it('real balance is not affected by sandbox movements', function () {
    $user = User::factory()->create();

    Movement::factory()->for($user)->create([
        'amount' => '1000.00',
        'is_projected' => false,
        'date' => now()->format('Y-m-d'),
    ]);

    Movement::factory()->for($user)->sandboxed()->create([
        'amount' => '5000.00',
        'is_projected' => false,
        'date' => now()->format('Y-m-d'),
    ]);

    expect(Movement::realBalance($user->id))->toBe('1000.00');
});
