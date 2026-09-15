<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSandboxGoalContributionRequest;
use App\Models\Goal;
use App\Models\GoalContribution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SandboxGoalContributionController extends Controller
{
    /**
     * Store a sandbox contribution against a real goal.
     * Does NOT call syncCompletion — sandbox contributions never complete a goal.
     */
    public function store(StoreSandboxGoalContributionRequest $request, Goal $goal): RedirectResponse
    {
        if ($goal->user_id !== $request->user()->id) {
            abort(403);
        }

        $goal->contributions()->create([
            ...$request->validated(),
            'is_sandbox' => true,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Aporte simulado registrado correctamente.',
        ]);

        return back();
    }

    /**
     * Remove a sandbox contribution. Does NOT call syncCompletion.
     */
    public function destroy(Request $request, Goal $goal, int $contribution): RedirectResponse
    {
        if ($goal->user_id !== $request->user()->id) {
            abort(403);
        }

        $contributionModel = GoalContribution::withoutSandboxScope()
            ->where('is_sandbox', true)
            ->findOrFail($contribution);

        if ($contributionModel->goal_id !== $goal->id) {
            abort(404);
        }

        $contributionModel->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Aporte simulado eliminado correctamente.',
        ]);

        return back();
    }
}
