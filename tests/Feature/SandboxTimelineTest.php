<?php

use App\Models\Category;
use App\Models\Movement;
use App\Models\User;

// ─── Movimientos index: sandboxMovements prop ──────────────────

it('includes sandboxMovements prop when sandbox rows exist', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    Movement::factory()->for($user)->create([
        'description' => 'Real',
        'category_id' => $category->id,
        'date' => now()->format('Y-m-d'),
    ]);

    Movement::factory()->for($user)->sandboxed()->create([
        'description' => 'Sandbox movement',
        'category_id' => $category->id,
        'date' => now()->format('Y-m-d'),
    ]);

    $response = $this->actingAs($user)->get(route('movimientos.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('sandboxMovements', 1)
        ->where('sandboxMovements.0.description', 'Sandbox movement')
    );
});

it('sandboxMovements is empty array when no sandbox rows exist', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('movimientos.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('sandboxMovements', 0)
    );
});

it('sandbox movements do not appear in realMovements or projectedMovements', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    Movement::factory()->for($user)->create([
        'description' => 'Real',
        'category_id' => $category->id,
        'date' => now()->format('Y-m-d'),
    ]);

    Movement::factory()->for($user)->sandboxed()->create([
        'description' => 'Sandbox',
        'category_id' => $category->id,
        'date' => now()->format('Y-m-d'),
    ]);

    $response = $this->actingAs($user)->get(route('movimientos.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('realMovements', fn ($m) => count($m) === 1 && $m[0]['description'] === 'Real')
        ->where('sandboxMovements', fn ($m) => count($m) === 1 && $m[0]['description'] === 'Sandbox')
    );
});

// ─── Projection: include_sandbox ───────────────────────────────

it('projection excludes sandbox by default', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    // Real projected movement (future)
    Movement::factory()->for($user)->create([
        'description' => 'Real projected',
        'category_id' => $category->id,
        'date' => now()->addMonth()->format('Y-m-d'),
        'is_projected' => true,
    ]);

    // Sandbox movement (future)
    Movement::factory()->for($user)->sandboxed()->create([
        'description' => 'Sandbox projected',
        'category_id' => $category->id,
        'date' => now()->addMonth()->format('Y-m-d'),
        'is_projected' => true,
    ]);

    $response = $this->actingAs($user)->get(route('proyeccion.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('includeSandbox', false)
        ->where('items', fn ($items) => count($items) === 1 && $items[0]['description'] === 'Real projected')
    );
});

it('projection includes sandbox when include_sandbox=1', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    Movement::factory()->for($user)->create([
        'description' => 'Real projected',
        'category_id' => $category->id,
        'date' => now()->addMonth()->format('Y-m-d'),
        'is_projected' => true,
    ]);

    Movement::factory()->for($user)->sandboxed()->create([
        'description' => 'Sandbox projected',
        'category_id' => $category->id,
        'date' => now()->addMonth()->format('Y-m-d'),
        'is_projected' => true,
    ]);

    $response = $this->actingAs($user)->get(route('proyeccion.index', ['include_sandbox' => 1]));

    $response->assertInertia(fn ($page) => $page
        ->where('includeSandbox', true)
        ->where('items', fn ($items) => count($items) === 2)
    );
});

it('projection items include is_sandbox flag', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    Movement::factory()->for($user)->create([
        'description' => 'Real',
        'category_id' => $category->id,
        'date' => now()->addMonth()->format('Y-m-d'),
        'is_projected' => true,
    ]);

    Movement::factory()->for($user)->sandboxed()->create([
        'description' => 'Sandbox',
        'category_id' => $category->id,
        'date' => now()->addMonth()->format('Y-m-d'),
        'is_projected' => true,
    ]);

    $response = $this->actingAs($user)->get(route('proyeccion.index', ['include_sandbox' => 1]));

    $response->assertInertia(fn ($page) => $page
        ->where('items', function ($items) {
            $real = collect($items)->firstWhere('description', 'Real');
            $sandbox = collect($items)->firstWhere('description', 'Sandbox');

            return ($real['is_sandbox'] ?? false) === false
                && ($sandbox['is_sandbox'] ?? false) === true;
        })
    );
});

it('projection running balance includes sandbox amounts when include_sandbox=1', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    // Real balance: 1000
    Movement::factory()->for($user)->create([
        'amount' => '1000.00',
        'is_projected' => false,
        'date' => now()->format('Y-m-d'),
    ]);

    // Future real: +500
    Movement::factory()->for($user)->create([
        'description' => 'Real future',
        'amount' => '500.00',
        'is_projected' => true,
        'date' => now()->addMonth()->format('Y-m-d'),
        'category_id' => $category->id,
    ]);

    // Future sandbox: +2000
    Movement::factory()->for($user)->sandboxed()->create([
        'description' => 'Sandbox future',
        'amount' => '2000.00',
        'is_projected' => true,
        'date' => now()->addMonth()->format('Y-m-d'),
        'category_id' => $category->id,
    ]);

    // Without sandbox: running balance = 1000 + 500 = 1500
    $response = $this->actingAs($user)->get(route('proyeccion.index'));
    $response->assertInertia(fn ($page) => $page
        ->where('items', fn ($items) => count($items) === 1 && abs($items[0]['running_balance'] - 1500) < 0.01)
    );

    // With sandbox: running balance = 1000 + 500 + 2000 = 3500
    $response = $this->actingAs($user)->get(route('proyeccion.index', ['include_sandbox' => 1]));
    $response->assertInertia(fn ($page) => $page
        ->where('items', function ($items) {
            $lastIndex = count($items) - 1;

            return $lastIndex >= 0 && abs($items[$lastIndex]['running_balance'] - 3500) < 0.01;
        })
    );
});
