<?php

use App\Models\Category;
use App\Models\Debt;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Helper to create a user with debt_category_id configured
function userWithDebtCategory(): array
{
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $user->update(['settings' => ['debt_category_id' => $category->id]]);

    return [$user->refresh(), $category];
}

// ─── Store ─────────────────────────────────────────────────────

it('creates a sandbox debt with disbursement and installment movements', function () {
    [$user, $category] = userWithDebtCategory();

    $disbursementDate = now()->format('Y-m-d');
    $paymentDates = [
        now()->addMonth()->format('Y-m-d'),
        now()->addMonths(2)->format('Y-m-d'),
        now()->addMonths(3)->format('Y-m-d'),
    ];

    $this->actingAs($user)
        ->post(route('simulacion.deudas.store'), [
            'name' => 'Préstamo simulado',
            'principal_amount' => '5000.00',
            'disbursement_date' => $disbursementDate,
            'installment_amount' => '500.00',
            'installments_count' => 3,
            'payment_dates' => $paymentDates,
        ])
        ->assertRedirect();

    // Debt exists and is sandbox
    $debt = Debt::withoutSandboxScope()
        ->where('user_id', $user->id)
        ->where('is_sandbox', true)
        ->first();

    expect($debt)->not->toBeNull()
        ->and($debt->name)->toBe('Préstamo simulado');

    // Real query does NOT see it
    expect(Debt::where('user_id', $user->id)->get())->toHaveCount(0);

    // Movements: 1 disbursement + 3 installments = 4, all sandbox
    $movements = Movement::withoutSandboxScope()
        ->where('user_id', $user->id)
        ->where('is_sandbox', true)
        ->where('debt_id', $debt->id)
        ->get();

    expect($movements)->toHaveCount(4);

    // Disbursement is positive
    $disbursement = $movements->first(fn ($m) => str_starts_with($m->description, 'Desembolso'));
    expect($disbursement)->not->toBeNull()
        ->and($disbursement->amount)->toBe('5000.00');
});

// ─── Accessors ─────────────────────────────────────────────────

it('computes accessors correctly for sandbox debt with sandbox movements', function () {
    [$user, $category] = userWithDebtCategory();

    $debt = Debt::factory()->for($user)->sandboxed()->create([
        'principal_amount' => '5000.00',
        'installment_amount' => '500.00',
        'installments_count' => 12,
    ]);

    // Create 3 paid installments (is_projected=false)
    for ($i = 0; $i < 3; $i++) {
        Movement::factory()->for($user)->sandboxed()->create([
            'amount' => '-500.00',
            'is_projected' => false,
            'debt_id' => $debt->id,
            'source' => 'manual',
        ]);
    }

    $debt->refresh();

    expect($debt->paid_installments)->toBe(3)
        ->and($debt->paid_amount)->toBe('1500.00')
        ->and($debt->remaining)->toBe('4500.00');
});

// ─── Payoff ────────────────────────────────────────────────────

it('pays off a sandbox debt with sandbox movement', function () {
    [$user, $category] = userWithDebtCategory();

    $debt = Debt::factory()->for($user)->sandboxed()->create([
        'principal_amount' => '5000.00',
        'installment_amount' => '500.00',
        'installments_count' => 12,
    ]);

    $this->actingAs($user)
        ->post(route('simulacion.deudas.payoff', $debt->id), [
            'amount' => '4500.00',
        ])
        ->assertRedirect();

    $debt->refresh();
    expect($debt->closed_at)->not->toBeNull();

    // Payoff movement is sandbox
    $payoffMovement = Movement::withoutSandboxScope()
        ->where('debt_id', $debt->id)
        ->where('description', 'like', 'Liquidación%')
        ->first();

    expect($payoffMovement)->not->toBeNull()
        ->and($payoffMovement->is_sandbox)->toBeTrue();
});

// ─── Destroy ───────────────────────────────────────────────────

it('deletes a sandbox debt without payments', function () {
    [$user, $category] = userWithDebtCategory();

    $debt = Debt::factory()->for($user)->sandboxed()->create();

    $this->actingAs($user)
        ->delete(route('simulacion.deudas.destroy', $debt->id))
        ->assertRedirect();

    expect(Debt::withoutSandboxScope()->where('id', $debt->id)->exists())->toBeFalse();
});

it('cannot delete sandbox debt with payments in the scenario', function () {
    [$user, $category] = userWithDebtCategory();

    $debt = Debt::factory()->for($user)->sandboxed()->create();

    // Create a non-projected sandbox movement (simulated payment)
    Movement::factory()->for($user)->sandboxed()->create([
        'amount' => '-500.00',
        'is_projected' => false,
        'debt_id' => $debt->id,
        'source' => 'manual',
    ]);

    $this->actingAs($user)
        ->delete(route('simulacion.deudas.destroy', $debt->id))
        ->assertStatus(409);
});

// ─── Isolation ─────────────────────────────────────────────────

it('sandbox endpoint returns 404 for real debt id', function () {
    [$user] = userWithDebtCategory();
    $realDebt = Debt::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('simulacion.deudas.destroy', $realDebt->id))
        ->assertNotFound();
});

it('real endpoint returns 404 for sandbox debt id', function () {
    [$user] = userWithDebtCategory();
    $sandboxDebt = Debt::factory()->for($user)->sandboxed()->create();

    $this->actingAs($user)
        ->delete(route('deudas.destroy', $sandboxDebt->id))
        ->assertNotFound();
});

it('sandbox debt does not appear in real debt index', function () {
    [$user] = userWithDebtCategory();

    Debt::factory()->for($user)->create(['name' => 'Real debt']);
    Debt::factory()->for($user)->sandboxed()->create(['name' => 'Sandbox debt']);

    $response = $this->actingAs($user)->get(route('deudas.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('debts', fn ($d) => count($d) === 1 && $d[0]['name'] === 'Real debt')
        ->where('sandboxDebts', fn ($d) => count($d) === 1 && $d[0]['name'] === 'Sandbox debt')
    );
});

// ─── Strategy with sandbox ─────────────────────────────────────

it('debt strategy includes sandbox debts when include_sandbox=1', function () {
    [$user, $category] = userWithDebtCategory();

    // Real debt
    $realDebt = Debt::factory()->for($user)->create([
        'name' => 'Real debt',
        'principal_amount' => '3000.00',
        'installment_amount' => '300.00',
        'installments_count' => 12,
    ]);

    // Sandbox debt
    Debt::factory()->for($user)->sandboxed()->create([
        'name' => 'Sandbox debt',
        'principal_amount' => '5000.00',
        'installment_amount' => '500.00',
        'installments_count' => 12,
    ]);

    // Without sandbox: strategy only has real debt
    $response = $this->actingAs($user)->get(route('deudas.show', $realDebt->id));
    $response->assertInertia(fn ($page) => $page
        ->where('includeSandbox', false)
    );

    // With sandbox: strategy includes both
    $response = $this->actingAs($user)->get(route('deudas.show', [$realDebt->id, 'include_sandbox' => 1]));
    $response->assertInertia(fn ($page) => $page
        ->where('includeSandbox', true)
    );
});
