<?php

namespace App\Http\Controllers;

use App\Http\Requests\MovementRequest;
use App\Models\Movement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SandboxMovementController extends Controller
{
    /**
     * Store a new sandbox movement.
     */
    public function store(MovementRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $isProjected = (bool) ($validated['is_projected'] ?? false);
        $date = $validated['date'];
        $sortOrder = Movement::nextSandboxSortOrder($request->user()->id, $date, $isProjected);

        $request->user()->movements()->create([
            ...$validated,
            'source' => 'manual',
            'is_sandbox' => true,
            'sort_order' => $sortOrder,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Movimiento simulado registrado correctamente.',
        ]);

        return back();
    }

    /**
     * Update a sandbox movement.
     */
    public function update(MovementRequest $request, int $id): RedirectResponse
    {
        $movement = Movement::withoutSandboxScope()
            ->where('is_sandbox', true)
            ->findOrFail($id);

        if ($movement->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validated();
        $isChangingToReal = $movement->is_projected && ! ($validated['is_projected'] ?? false);
        $isDateChanged = isset($validated['date']) && $validated['date'] !== $movement->date->toDateString();

        if ($isChangingToReal || $isDateChanged) {
            $targetDate = $validated['date'] ?? $movement->date->toDateString();
            $targetProjected = (bool) ($validated['is_projected'] ?? $movement->is_projected);
            $validated['sort_order'] = Movement::nextSandboxSortOrder(
                $request->user()->id,
                $targetDate,
                $targetProjected
            );
        }

        $movement->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Movimiento simulado actualizado correctamente.',
        ]);

        return back();
    }

    /**
     * Delete a sandbox movement.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $movement = Movement::withoutSandboxScope()
            ->where('is_sandbox', true)
            ->findOrFail($id);

        if ($movement->user_id !== $request->user()->id) {
            abort(403);
        }

        $movement->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Movimiento simulado eliminado correctamente.',
        ]);

        return back();
    }
}
