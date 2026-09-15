<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayoffDebtRequest;
use App\Http\Requests\StoreDebtRequest;
use App\Http\Requests\UpdateDebtRequest;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Movement;
use App\Models\User;
use App\Services\DebtStrategy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DebtController extends Controller
{
    /**
     * Display a listing of debts.
     */
    public function index(Request $request): Response
    {
        $debts = Debt::where('user_id', $request->user()->id)
            ->withCount(['movements as real_movements_count' => function ($q) {
                $q->where('is_projected', false);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        $sandboxDebts = Debt::withoutSandboxScope()
            ->where('user_id', $request->user()->id)
            ->where('is_sandbox', true)
            ->withCount(['movements as real_movements_count' => function ($q) {
                $q->where('is_projected', false);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Deudas/Index', [
            'debts' => $debts->map(fn (Debt $debt) => [
                'id' => $debt->id,
                'name' => $debt->name,
                'principal_amount' => (float) $debt->principal_amount,
                'disbursement_date' => $debt->disbursement_date->format('Y-m-d'),
                'installment_amount' => (float) $debt->installment_amount,
                'installments_count' => $debt->installments_count,
                'payment_dates' => $debt->payment_dates,
                'closed_at' => $debt->closed_at?->toDateTimeString(),
                'rate_factor' => $debt->rate_factor,
                'total_to_pay' => (float) $debt->total_to_pay,
                'paid_installments' => $debt->paid_installments,
                'remaining' => (float) $debt->remaining,
                'can_delete' => $debt->real_movements_count === 0,
                'is_active' => $debt->is_active,
            ]),
            'sandboxDebts' => $sandboxDebts->map(fn (Debt $debt) => [
                'id' => $debt->id,
                'name' => $debt->name,
                'principal_amount' => (float) $debt->principal_amount,
                'disbursement_date' => $debt->disbursement_date->format('Y-m-d'),
                'installment_amount' => (float) $debt->installment_amount,
                'installments_count' => $debt->installments_count,
                'payment_dates' => $debt->payment_dates,
                'closed_at' => $debt->closed_at?->toDateTimeString(),
                'rate_factor' => $debt->rate_factor,
                'total_to_pay' => (float) $debt->total_to_pay,
                'paid_installments' => $debt->paid_installments,
                'remaining' => (float) $debt->remaining,
                'can_delete' => $debt->real_movements_count === 0,
                'is_active' => $debt->is_active,
                'is_sandbox' => true,
            ]),
        ]);
    }

    /**
     * Display the debt detail: totals, payment history and schedule.
     */
    public function show(Request $request, Debt $debt): Response
    {
        if ($debt->user_id !== $request->user()->id) {
            abort(403);
        }

        $mapMovement = fn (Movement $movement): array => [
            'id' => $movement->id,
            'date' => $movement->date->toDateString(),
            'description' => $movement->description,
            'category_id' => $movement->category_id,
            'category_name' => $movement->category?->name,
            'category_color' => $movement->category?->color,
            'amount' => (float) $movement->amount,
            'is_projected' => (bool) $movement->is_projected,
            'notes' => $movement->notes,
        ];

        $paymentHistory = $debt->movements()
            ->where('is_projected', false)
            ->with('category')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map($mapMovement)
            ->values();

        $schedule = $debt->movements()
            ->where('is_projected', true)
            ->with('category')
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map($mapMovement)
            ->values();

        $includeSandbox = (bool) $request->boolean('include_sandbox');

        $activeDebtsQuery = Debt::where('user_id', $request->user()->id)->whereNull('closed_at');

        if ($includeSandbox) {
            $activeDebtsQuery = Debt::withoutSandboxScope()
                ->where('user_id', $request->user()->id)
                ->whereNull('closed_at');
        }

        $activeDebts = $activeDebtsQuery->get();

        $strategy = new DebtStrategy(array_values(
            $activeDebts->map(fn (Debt $activeDebt): array => [
                'id' => $activeDebt->id,
                'name' => $activeDebt->name,
                'remaining' => (float) $activeDebt->remaining,
                'factor' => $activeDebt->rate_factor,
                'installment' => (float) $activeDebt->installment_amount,
                'is_sandbox' => (bool) $activeDebt->is_sandbox,
            ])->all(),
        ));

        return Inertia::render('Deudas/Show', [
            'debt' => [
                'id' => $debt->id,
                'name' => $debt->name,
                'principal_amount' => (float) $debt->principal_amount,
                'disbursement_date' => $debt->disbursement_date->format('Y-m-d'),
                'installment_amount' => (float) $debt->installment_amount,
                'installments_count' => $debt->installments_count,
                'payment_dates' => $debt->payment_dates,
                'closed_at' => $debt->closed_at?->toDateTimeString(),
                'rate_factor' => $debt->rate_factor,
                'total_to_pay' => (float) $debt->total_to_pay,
                'paid' => (float) $debt->paid_amount,
                'paid_installments' => $debt->paid_installments,
                'remaining' => (float) $debt->remaining,
                'is_active' => $debt->is_active,
            ],
            'payment_history' => $paymentHistory->all(),
            'schedule' => $schedule->all(),
            'strategy' => [
                'avalanche' => $strategy->avalancheOrder(),
                'snowball' => $strategy->snowballOrder(),
                'weighted_factor' => $strategy->weightedFactor(),
            ],
            'includeSandbox' => $includeSandbox,
        ]);
    }

    /**
     * Resolve the user's configured category for loan movements.
     * Returns null when the setting is missing or points to a category
     * that no longer belongs to the user.
     */
    private static function resolveLoanCategory(User $user): ?Category
    {
        $categoryId = $user->settings['debt_category_id'] ?? null;

        if (! $categoryId) {
            return null;
        }

        return Category::where('user_id', $user->id)
            ->where('id', $categoryId)
            ->first();
    }

    /**
     * Store a newly created debt with its movements in a transaction.
     */
    public function store(StoreDebtRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $loanCategory = self::resolveLoanCategory($user);

        if ($loanCategory === null) {
            throw ValidationException::withMessages([
                'debt_category' => 'Configura una categoría para tus préstamos en Preferencias antes de crear una deuda.',
            ]);
        }

        DB::transaction(function () use ($validated, $user, $loanCategory) {
            // Create the debt
            $debt = $user->debts()->create([
                'name' => $validated['name'],
                'principal_amount' => $validated['principal_amount'],
                'disbursement_date' => $validated['disbursement_date'],
                'installment_amount' => $validated['installment_amount'],
                'installments_count' => $validated['installments_count'],
                'payment_dates' => $validated['payment_dates'],
            ]);

            // Create disbursement movement (+principal)
            $disbursementDate = Carbon::parse($validated['disbursement_date']);
            $isProjected = $disbursementDate->isFuture();

            $user->movements()->create([
                'date' => $validated['disbursement_date'],
                'description' => "Desembolso {$debt->name}",
                'category_id' => $loanCategory->id,
                'amount' => $validated['principal_amount'],
                'source' => 'manual',
                'debt_id' => $debt->id,
                'is_projected' => $isProjected,
                'sort_order' => Movement::nextSortOrder($user->id, $validated['disbursement_date'], $isProjected),
            ]);

            // Create installment movements (-installment)
            foreach ($validated['payment_dates'] as $index => $paymentDate) {
                $paymentDateCarbon = Carbon::parse($paymentDate);
                $isProjected = $paymentDateCarbon->isFuture();
                $installmentNumber = $index + 1;

                $user->movements()->create([
                    'date' => $paymentDate,
                    'description' => "Cuota {$debt->name} ({$installmentNumber}/{$validated['installments_count']})",
                    'category_id' => $loanCategory->id,
                    'amount' => -$validated['installment_amount'],
                    'source' => 'manual',
                    'debt_id' => $debt->id,
                    'is_projected' => $isProjected,
                    'sort_order' => Movement::nextSortOrder($user->id, $paymentDate, $isProjected),
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Deuda creada correctamente.',
        ]);

        return back();
    }

    /**
     * Update the specified debt and regenerate projected movements.
     */
    public function update(UpdateDebtRequest $request, Debt $debt): RedirectResponse
    {
        if ($debt->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($validated, $debt, $user) {
            // Update debt fields
            $debt->update($validated);

            // Delete only projected movements linked to this debt
            $debt->movements()->where('is_projected', true)->delete();

            $loanCategory = self::resolveLoanCategory($user);

            // Regenerate projected movements for disbursement if future
            $disbursementDate = Carbon::parse($debt->disbursement_date);
            if ($disbursementDate->isFuture()) {
                $user->movements()->create([
                    'date' => $disbursementDate->toDateString(),
                    'description' => "Desembolso {$debt->name}",
                    'category_id' => $loanCategory?->id,
                    'amount' => $debt->principal_amount,
                    'source' => 'manual',
                    'debt_id' => $debt->id,
                    'is_projected' => true,
                    'sort_order' => Movement::nextSortOrder($user->id, $disbursementDate->toDateString(), true),
                ]);
            }

            // Regenerate projected installment movements
            foreach ($debt->payment_dates as $index => $paymentDate) {
                $paymentDateCarbon = Carbon::parse($paymentDate);
                if ($paymentDateCarbon->isFuture()) {
                    $installmentNumber = $index + 1;

                    $user->movements()->create([
                        'date' => $paymentDate,
                        'description' => "Cuota {$debt->name} ({$installmentNumber}/{$debt->installments_count})",
                        'category_id' => $loanCategory?->id,
                        'amount' => -$debt->installment_amount,
                        'source' => 'manual',
                        'debt_id' => $debt->id,
                        'is_projected' => true,
                        'sort_order' => Movement::nextSortOrder($user->id, $paymentDate, true),
                    ]);
                }
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Deuda actualizada correctamente.',
        ]);

        return back();
    }

    /**
     * Pay off the debt early with a single movement.
     */
    public function payoff(PayoffDebtRequest $request, Debt $debt): RedirectResponse
    {
        if ($debt->user_id !== $request->user()->id) {
            abort(403);
        }

        if (! $debt->is_active) {
            abort(422, 'La deuda ya está cerrada.');
        }

        $validated = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($validated, $debt, $user) {
            $loanCategory = self::resolveLoanCategory($user);

            // Create payoff movement
            $user->movements()->create([
                'date' => now()->toDateString(),
                'description' => "Liquidación anticipada {$debt->name}",
                'category_id' => $loanCategory?->id,
                'amount' => -$validated['amount'],
                'source' => 'manual',
                'debt_id' => $debt->id,
                'is_projected' => false,
                'sort_order' => Movement::nextSortOrder($user->id, now()->toDateString(), false),
            ]);

            // Delete remaining projected movements
            $debt->movements()->where('is_projected', true)->delete();

            // Close the debt
            $debt->update(['closed_at' => now()]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Deuda liquidada correctamente.',
        ]);

        return back();
    }

    /**
     * Remove the specified debt only if no real payments exist.
     */
    public function destroy(Request $request, Debt $debt): RedirectResponse
    {
        if ($debt->user_id !== $request->user()->id) {
            abort(403);
        }

        // Check if there are any real (non-projected) movements
        $hasRealPayments = $debt->movements()
            ->where('is_projected', false)
            ->exists();

        if ($hasRealPayments) {
            abort(409, 'No se puede eliminar una deuda con pagos registrados.');
        }

        // Delete projected movements and the debt
        DB::transaction(function () use ($debt) {
            $debt->movements()->where('is_projected', true)->delete();
            $debt->delete();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Deuda eliminada correctamente.',
        ]);

        return back();
    }
}
