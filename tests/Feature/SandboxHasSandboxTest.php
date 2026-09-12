<?php

use App\Models\Debt;
use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\Movement;
use App\Models\RecurringTransaction;
use App\Models\User;

// ─── hasSandbox shared prop ────────────────────────────────────

it('shares hasSandbox=false when no sandbox rows exist', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->where('hasSandbox', false));
});

it('shares hasSandbox=true when a sandbox movement exists', function () {
    $user = User::factory()->create();
    Movement::factory()->for($user)->sandboxed()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->where('hasSandbox', true));
});

it('shares hasSandbox=true when a sandbox recurring transaction exists', function () {
    $user = User::factory()->create();
    RecurringTransaction::factory()->for($user)->sandboxed()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->where('hasSandbox', true));
});

it('shares hasSandbox=true when a sandbox debt exists', function () {
    $user = User::factory()->create();
    Debt::factory()->for($user)->sandboxed()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->where('hasSandbox', true));
});

it('shares hasSandbox=true when a sandbox goal contribution exists', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create();
    GoalContribution::factory()->for($goal)->sandboxed()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->where('hasSandbox', true));
});

it('shares hasSandbox=false after sandbox rows are deleted', function () {
    $user = User::factory()->create();
    $movement = Movement::factory()->for($user)->sandboxed()->create();

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('hasSandbox', true));

    Movement::withoutSandboxScope()->where('id', $movement->id)->delete();

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('hasSandbox', false));
});

it('does not count real rows as sandbox', function () {
    $user = User::factory()->create();
    Movement::factory()->for($user)->create(['is_sandbox' => false]);
    RecurringTransaction::factory()->for($user)->create(['is_sandbox' => false]);
    Debt::factory()->for($user)->create(['is_sandbox' => false]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->where('hasSandbox', false));
});

it('hasSandbox is false for a fresh user with only real data', function () {
    $user = User::factory()->create();
    Movement::factory()->for($user)->count(3)->create();
    Debt::factory()->for($user)->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->where('hasSandbox', false));
});
