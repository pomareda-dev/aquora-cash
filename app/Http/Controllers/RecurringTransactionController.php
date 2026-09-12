<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecurringRequest;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Services\ProjectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurringTransactionController extends Controller
{
    /**
     * Display a listing of recurring transactions.
     */
    public function index(Request $request): Response
    {
        $templates = RecurringTransaction::where('user_id', $request->user()->id)
            ->with('category')
            ->orderBy('created_at', 'desc')
            ->get();

        $sandboxTemplates = RecurringTransaction::withoutSandboxScope()
            ->where('user_id', $request->user()->id)
            ->where('is_sandbox', true)
            ->with('category')
            ->orderBy('created_at', 'desc')
            ->get();

        $categories = Category::where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name', 'kind', 'color']);

        return Inertia::render('Recurrentes/Index', [
            'templates' => $templates->map(fn (RecurringTransaction $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'amount' => (float) $t->amount,
                'category_id' => $t->category_id,
                'category_name' => $t->category?->name,
                'day_of_month' => $t->day_of_month,
                'start_month' => $t->start_month->format('Y-m-d'),
                'end_month' => $t->end_month?->format('Y-m-d'),
                'active' => $t->active,
            ]),
            'sandboxTemplates' => $sandboxTemplates->map(fn (RecurringTransaction $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'amount' => (float) $t->amount,
                'category_id' => $t->category_id,
                'category_name' => $t->category?->name,
                'day_of_month' => $t->day_of_month,
                'start_month' => $t->start_month->format('Y-m-d'),
                'end_month' => $t->end_month?->format('Y-m-d'),
                'active' => $t->active,
                'is_sandbox' => true,
            ]),
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created recurring transaction.
     */
    public function store(RecurringRequest $request): RedirectResponse
    {
        $request->user()->recurringTransactions()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Plantilla recurrente creada correctamente.',
        ]);

        return back();
    }

    /**
     * Update the specified recurring transaction.
     */
    public function update(RecurringRequest $request, RecurringTransaction $recurringTransaction): RedirectResponse
    {
        if ($recurringTransaction->user_id !== $request->user()->id) {
            abort(403);
        }

        $recurringTransaction->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Plantilla recurrente actualizada correctamente.',
        ]);

        return back();
    }

    /**
     * Remove the specified recurring transaction.
     */
    public function destroy(Request $request, RecurringTransaction $recurringTransaction): RedirectResponse
    {
        if ($recurringTransaction->user_id !== $request->user()->id) {
            abort(403);
        }

        // Remove the template's projected movements; realized movements
        // (is_projected=false) are real history and must be preserved.
        $recurringTransaction->movements()
            ->where('is_projected', true)
            ->delete();

        $recurringTransaction->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Plantilla recurrente eliminada correctamente.',
        ]);

        return back();
    }

    /**
     * Regenerate projected movements from all active recurring templates.
     */
    public function regenerate(Request $request, ProjectionService $projectionService): RedirectResponse
    {
        $count = $projectionService->regenerateForUser($request->user()->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Proyecciones regeneradas: {$count} movimientos creados.",
        ]);

        return back();
    }
}
