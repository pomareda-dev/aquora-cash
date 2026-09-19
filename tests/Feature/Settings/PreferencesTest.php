<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

// ─── Settings: Update (PUT /settings) ──────────────────────────────

test('settings update persists valid values', function (string $field, mixed $value) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.update'), [$field => $value])
        ->assertNoContent();

    $user->refresh();

    expect($user->settings[$field])->toEqual($value);
})->with([
    'density' => ['density', 'compact'],
    'start_section' => ['start_section', 'movements'],
    'projection_horizon' => ['projection_horizon', 6],
]);

test('settings update rejects invalid values', function (string $field, mixed $value) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.update'), [$field => $value])
        ->assertSessionHasErrors($field);
})->with([
    'density cozy' => ['density', 'cozy'],
    'start_section wallet' => ['start_section', 'wallet'],
    'horizon 0' => ['projection_horizon', 0],
    'horizon 25' => ['projection_horizon', 25],
    'horizon abc' => ['projection_horizon', 'abc'],
]);

test('settings update ignores unknown keys', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.update'), [
            'density' => 'compact',
            'is_admin' => true,
        ])
        ->assertNoContent();

    $user->refresh();

    expect($user->settings['density'])->toBe('compact');
    expect($user->settings)->not->toHaveKey('is_admin');
});

test('settings update merges with existing settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.update'), ['start_section' => 'movements'])
        ->assertNoContent();

    $this->actingAs($user)
        ->put(route('settings.update'), ['density' => 'compact'])
        ->assertNoContent();

    $user->refresh();

    expect($user->settings['start_section'])->toBe('movements');
    expect($user->settings['density'])->toBe('compact');
});

test('settings update persists the configured debt category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->put(route('settings.update'), ['debt_category_id' => $category->id])
        ->assertNoContent();

    $user->refresh();

    expect($user->settings['debt_category_id'])->toBe($category->id);
});

test('settings update rejects a debt category from another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherCategory = Category::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->put(route('settings.update'), ['debt_category_id' => $otherCategory->id])
        ->assertSessionHasErrors('debt_category_id');
});

test('settings update accepts null to unset the debt category', function () {
    $user = User::factory()->create([
        'settings' => ['debt_category_id' => null],
    ]);

    $this->actingAs($user)
        ->put(route('settings.update'), ['debt_category_id' => null])
        ->assertNoContent();

    $user->refresh();

    expect($user->settings)->toHaveKey('debt_category_id');
    expect($user->settings['debt_category_id'])->toBeNull();
});

test('preferences page renders the users categories for the debt picker', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Préstamos',
        'kind' => 'expense',
    ]);

    $response = $this->actingAs($user)->get(route('preferences.edit'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('settings/Preferences')
        ->has('categories', 1)
        ->where('categories.0.id', $category->id)
        ->where('categories.0.name', 'Préstamos'));
});

// ─── Settings: Onboarding Tour Flag ───────────────────────────────

test('settings update persists the onboarding flag', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.update'), [
            'onboarding' => [
                'completed_at' => '2026-09-15T12:00:00Z',
                'version' => 1,
            ],
        ])
        ->assertNoContent();

    $user->refresh();

    expect($user->settings['onboarding']['completed_at'])->toBe('2026-09-15T12:00:00Z');
    expect($user->settings['onboarding']['version'])->toBe(1);
});

test('settings update rejects an invalid onboarding version', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.update'), ['onboarding' => ['version' => 0]])
        ->assertSessionHasErrors('onboarding.version');
});

test('settings update rejects an invalid onboarding completion date', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.update'), ['onboarding' => ['completed_at' => 'not-a-date']])
        ->assertSessionHasErrors('onboarding.completed_at');
});

test('settings update preserves the onboarding flag when updating other settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.update'), [
            'onboarding' => [
                'completed_at' => '2026-09-15T12:00:00Z',
                'version' => 1,
            ],
        ])
        ->assertNoContent();

    $this->actingAs($user)
        ->put(route('settings.update'), ['density' => 'compact'])
        ->assertNoContent();

    $user->refresh();

    expect($user->settings['density'])->toBe('compact');
    expect($user->settings['onboarding']['completed_at'])->toBe('2026-09-15T12:00:00Z');
    expect($user->settings['onboarding']['version'])->toBe(1);
});

test('settings update re-arms the onboarding flag', function () {
    $user = User::factory()->create([
        'settings' => ['onboarding' => ['completed_at' => '2026-09-15T12:00:00Z', 'version' => 1]],
    ]);

    $this->actingAs($user)
        ->put(route('settings.update'), [
            'onboarding' => ['completed_at' => null, 'version' => 1],
        ])
        ->assertNoContent();

    $user->refresh();

    expect($user->settings['onboarding'])->toHaveKey('completed_at');
    expect($user->settings['onboarding']['completed_at'])->toBeNull();
    expect($user->settings['onboarding']['version'])->toBe(1);
});

// ─── Settings: Removed theme and profile photo features ────────────

test('settings update no longer accepts theme or avatar_path', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.update'), [
            'theme' => 'claude',
            'avatar_path' => 'avatars/1.jpg',
        ])
        ->assertNoContent();

    $user->refresh();

    expect($user->settings ?? [])->not->toHaveKey('theme');
    expect($user->settings ?? [])->not->toHaveKey('avatar_path');
});

test('removed appearance and profile photo routes are not registered', function () {
    expect(Route::has('appearance.edit'))->toBeFalse();
    expect(Route::has('config.profile-photo.store'))->toBeFalse();
});

// ─── Login Redirect (via Fortify LoginResponse) ────────────────────

test('login redirects to dashboard when no start_section setting', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('login redirects to start_section movements', function () {
    $user = User::factory()->create([
        'settings' => ['start_section' => 'movements'],
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('movimientos.index', absolute: false));
});
