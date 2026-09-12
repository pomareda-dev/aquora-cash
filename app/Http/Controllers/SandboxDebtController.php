<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayoffDebtRequest;
use App\Http\Requests\StoreDebtRequest;
use App\Http\Requests\UpdateDebtRequest;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SandboxDebtController extends Controller
{
    /**
     * Resolve the user's configured category for loan movements.
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
     * Store a new sandbox debt with its movements.
     */
    public function store(StoreDebtRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $loanCategory = self::resolveLoanCategory($user);

        if ($loanCategory === null) {
            throw ValidationException::withMessages([
                'debt_category' => 'Configura una categoría para tus préstamos en Preferencias antes de crear una deuda simulada.',
            ]);
        }

        DB::transaction(function () use ($validated, $user, $loanCategory) {
            $debt = $user->debts()->create([
                ...$validated,
                'is_sandbox' => true,
            ]);

            // Disbursement movement (+principal)
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
                'is_sandbox' => true,
                'sort_order' => Movement::nextSandboxSortOrder($user->id, $validated['disbursement_date'], $isProjected),
            ]);

            // Installment movements (-installment)
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
                    'is_sandbox' => true,
                    'sort_order' => Movement::nextSandboxSortOrder($user->id, $paymentDate, $isProjected),
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Deuda simulada creada correctamente.',
        ]);

        return back();
    }

    /**
     * Update a sandbox debt and regenerate its projected movements.
     */
    public function update(UpdateDebtRequest $request, int $id): RedirectResponse
    {
        $debt = Debt::withoutSandboxScope()
            ->where('is_sandbox', true)
            ->findOrFail($id);

        if ($debt->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($validated, $debt, $user) {
            $debt->update($validated);

            // Delete projected movements linked to this debt
            $debt->movements()->where('is_projected', true)->delete();

            $loanCategory = self::resolveLoanCategory($user);

            // Regenerate projected disbursement if future
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
                    'is_sandbox' => true,
                    'sort_order' => Movement::nextSandboxSortOrder($user->id, $disbursementDate->toDateString(), true),
                ]);
            }

            // Regenerate projected installments
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
                        'is_sandbox' => true,
                        'sort_order' => Movement::nextSandboxSortOrder($user->id, $paymentDate, true),
                    ]);
                }
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Deuda simulada actualizada correctamente.',
        ]);

        return back();
    }

    /**
     * Pay off a sandbox debt early.
     */
    public function payoff(PayoffDebtRequest $request, int $id): RedirectResponse
    {
        $debt = Debt::withoutSandboxScope()
            ->where('is_sandbox', true)
            ->findOrFail($id);

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

            // Create payoff movement (sandbox)
            $user->movements()->create([
                'date' => now()->toDateString(),
                'description' => "Liquidación anticipada {$debt->name}",
                'category_id' => $loanCategory?->id,
                'amount' => -$validated['amount'],
                'source' => 'manual',
                'debt_id' => $debt->id,
                'is_projected' => false,
                'is_sandbox' => true,
                'sort_order' => Movement::nextSandboxSortOrder($user->id, now()->toDateString(), false),
            ]);

            // Delete remaining projected movements
            $debt->movements()->where('is_projected', true)->delete();

            // Close the debt
            $debt->update(['closed_at' => now()]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Deuda simulada liquidada correctamente.',
        ]);

        return back();
    }

    /**
     * Remove a sandbox debt only if no real payments exist.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $debt = Debt::withoutSandboxScope()
            ->where('is_sandbox', true)
            ->findOrFail($id);

        if ($debt->user_id !== $request->user()->id) {
            abort(403);
        }

        // For sandbox debts, "real payments" means non-projected sandbox movements
        $hasRealPayments = $debt->movements()
            ->where('is_projected', false)
            ->exists();

        if ($hasRealPayments) {
            abort(409, 'No se puede eliminar una deuda simulada con pagos registrados en el escenario.');
        }

        DB::transaction(function () use ($debt) {
            $debt->movements()->where('is_projected', true)->delete();
            $debt->delete();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Deuda simulada eliminada correctamente.',
        ]);

        return back();
    }
}
