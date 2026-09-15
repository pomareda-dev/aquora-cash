<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecurringRequest;
use App\Models\RecurringTransaction;
use App\Services\ProjectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SandboxRecurringController extends Controller
{
    /**
     * Store a newly created sandbox recurring template.
     */
    public function store(RecurringRequest $request, ProjectionService $projectionService): RedirectResponse
    {
        $request->user()->recurringTransactions()->create([
            ...$request->validated(),
            'is_sandbox' => true,
        ]);

        $projectionService->generateSandboxForUser($request->user()->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Plantilla recurrente simulada creada correctamente.',
        ]);

        return back();
    }

    /**
     * Update the specified sandbox recurring template.
     */
    public function update(RecurringRequest $request, int $id, ProjectionService $projectionService): RedirectResponse
    {
        $template = RecurringTransaction::withoutSandboxScope()
            ->where('is_sandbox', true)
            ->findOrFail($id);

        if ($template->user_id !== $request->user()->id) {
            abort(403);
        }

        $template->update($request->validated());

        // Delete sandbox projected movements for this template and regenerate
        $template->movements()
            ->where('is_projected', true)
            ->delete();

        $projectionService->generateSandboxForUser($request->user()->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Plantilla recurrente simulada actualizada correctamente.',
        ]);

        return back();
    }

    /**
     * Remove the specified sandbox recurring template.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $template = RecurringTransaction::withoutSandboxScope()
            ->where('is_sandbox', true)
            ->findOrFail($id);

        if ($template->user_id !== $request->user()->id) {
            abort(403);
        }

        // Delete sandbox projected movements, then the template
        $template->movements()
            ->where('is_projected', true)
            ->delete();

        $template->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Plantilla recurrente simulada eliminada correctamente.',
        ]);

        return back();
    }

    /**
     * Regenerate sandbox projected movements from all active sandbox templates.
     */
    public function regenerate(Request $request, ProjectionService $projectionService): RedirectResponse
    {
        $count = $projectionService->regenerateSandboxForUser($request->user()->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Proyecciones simuladas regeneradas: {$count} movimientos creados.",
        ]);

        return back();
    }
}
